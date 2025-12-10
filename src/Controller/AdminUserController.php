<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Family;
use App\Entity\Message;
use App\Entity\User;
use App\Form\ProfileFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/administration')]
class AdminUserController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    private function assertAdmin2FA(Request $request): ?Response
    {
        $user = $this->getUser();
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('app_login');
        }
        if (method_exists($user, 'isTwoFactorEnabled') && $user->isTwoFactorEnabled()) {
            $verified = (bool)$request->getSession()->get('admin_2fa_verified');
            if (!$verified) {
                return $this->redirectToRoute('admin_2fa');
            }
        }
        return null;
    }

    #[Route('/utilisateurs', name: 'admin_users')]
    public function users(Request $request, UserRepository $users): Response
    {
        if ($resp = $this->assertAdmin2FA($request)) { return $resp; }
        $q = trim((string)$request->query->get('q', ''));
        $qb = $users->createQueryBuilder('u')->orderBy('u.email', 'ASC');
        if ($q !== '') {
            $qb->andWhere('LOWER(u.email) LIKE :q OR LOWER(u.firstName) LIKE :q OR LOWER(u.lastName) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($q).'%');
        }
        $list = $qb->setMaxResults(200)->getQuery()->getResult();
        return $this->render('administration/users.html.twig', [
            'users' => $list,
            'q' => $q,
        ]);
    }

    #[Route('/utilisateur/{id}', name: 'admin_user_edit')]
    public function userEdit(int $id, Request $request, \App\Service\BirthdaySync $birthdaySync): Response
    {
        if ($resp = $this->assertAdmin2FA($request)) { return $resp; }
        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_users');
        }

        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            try {
                if (method_exists($user, 'getFamilies')) {
                    foreach ($user->getFamilies() as $fam) {
                        $birthdaySync->syncForUserInFamily($user, $fam);
                    }
                }
            } catch (\Throwable $e) {}
            $this->addFlash('success', 'Profil utilisateur mis à jour.');
            return $this->redirectToRoute('admin_user_edit', ['id' => $id]);
        }

        return $this->render('administration/user_edit.html.twig', [
            'userForm' => $form->createView(),
            'userEntity' => $user,
        ]);
    }

    #[Route('/utilisateur/{id}/supprimer', name: 'admin_user_delete', methods: ['POST'])]
    public function userDelete(int $id, Request $request): Response
    {
        if ($resp = $this->assertAdmin2FA($request)) { return $resp; }
        $current = $this->getUser();
        $target = $this->em->getRepository(User::class)->find($id);
        if (!$target) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_users');
        }
        if (!$this->isCsrfTokenValid('admin_delete_user_'.$id, (string)$request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_user_edit', ['id' => $id]);
        }
        // Ne pas supprimer un compte administrateur via cette interface
        if (in_array('ROLE_ADMIN', $target->getRoles(), true)) {
            $this->addFlash('error', 'Impossible de supprimer un compte administrateur depuis cette interface.');
            return $this->redirectToRoute('admin_user_edit', ['id' => $id]);
        }
        // Ne pas permettre à un admin de se supprimer lui-même ici
        if ($current && $target->getId() === $current->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte depuis cette page.');
            return $this->redirectToRoute('admin_user_edit', ['id' => $id]);
        }

        // 1) Supprimer familles dont il est propriétaire
        $families = $this->em->getRepository(Family::class)->findBy(['owner' => $target]);
        foreach ($families as $f) { $this->em->remove($f); }
        // 2) Supprimer messages de l'utilisateur (pour éviter contrainte FK)
        $msgs = $this->em->getRepository(Message::class)->findBy(['author' => $target]);
        foreach ($msgs as $m) { $this->em->remove($m); }
        // 3) Détacher auteur des événements
        $events = $this->em->getRepository(Event::class)->findBy(['createdBy' => $target]);
        foreach ($events as $e) { $e->setCreatedBy(null); }
        // 4) Retirer des familles (membership)
        if (method_exists($target, 'getFamilies')) {
            foreach ($target->getFamilies() as $fam) { $target->removeFamily($fam); }
        }
        // 5) Supprimer l'utilisateur
        $this->em->remove($target);
        $this->em->flush();

        $this->addFlash('success', 'Compte utilisateur supprimé.');
        return $this->redirectToRoute('admin_users');
    }
}

