<?php

namespace App\Controller;

use App\Form\ProfileFormType;
use App\Entity\Family;
use App\Entity\Message;
use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends AbstractController
{
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) { return $this->redirectToRoute('app_login'); }

        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');
            return $this->redirectToRoute('profile_home');
        }

        return $this->render('profile/index.html.twig', [
            'profileForm' => $form->createView(),
        ]);
    }

    public function delete(Request $request, EntityManagerInterface $em, \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface $tokens): Response
    {
        $user = $this->getUser();
        if (!$user) { return $this->redirectToRoute('app_login'); }
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $this->addFlash('error', "Le compte administrateur ne peut pas être supprimé ici.");
            return $this->redirectToRoute('profile_home');
        }
        if (!$this->isCsrfTokenValid('delete_account', (string)$request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('profile_home');
        }
        // 1) Supprimer familles dont il est propriétaire
        $families = $em->getRepository(Family::class)->findBy(['owner' => $user]);
        foreach ($families as $f) { $em->remove($f); }
        // 2) Supprimer messages de l'utilisateur (pour éviter contrainte FK)
        $msgs = $em->getRepository(Message::class)->findBy(['author' => $user]);
        foreach ($msgs as $m) { $em->remove($m); }
        // 3) Détacher auteur des événements
        $events = $em->getRepository(Event::class)->findBy(['createdBy' => $user]);
        foreach ($events as $e) { $e->setCreatedBy(null); }
        // 4) Retirer des familles (membership)
        if (method_exists($user, 'getFamilies')) {
            foreach ($user->getFamilies() as $fam) { $user->removeFamily($fam); }
        }
        // 5) Supprimer l'utilisateur
        $em->remove($user);
        $em->flush();
        // 6) Déconnecter proprement (vider le token et la session pour éviter l'erreur de rafraîchissement d'utilisateur)
        $tokens->setToken(null);
        if ($request->hasSession()) { $request->getSession()->invalidate(); }
        return $this->redirectToRoute('homepage', ['deleted' => 1]);
    }
}
