<?php

namespace App\Controller;

use App\Entity\Family;
use App\Entity\Photo;
use App\Entity\User;
use App\Entity\ContactMessage;
use App\Entity\Message;
use App\Entity\Event;
use App\Repository\FamilyRepository;
use App\Repository\UserRepository;
use App\Form\ProfileFormType;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/administration')]
class AdminController extends AbstractController
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

    #[Route('', name: 'admin_home')]
    public function index(Request $request, FamilyRepository $families): Response
    {
        if ($resp = $this->assertAdmin2FA($request)) { return $resp; }
        $q = trim((string)$request->query->get('q', ''));
        $qb = $families->createQueryBuilder('f')->orderBy('f.name', 'ASC');
        if ($q !== '') {
            $qb->andWhere('LOWER(f.name) LIKE :q')->setParameter('q', '%'.mb_strtolower($q).'%');
        }
        $list = $qb->setMaxResults(200)->getQuery()->getResult();
        return $this->render('administration/index.html.twig', [ 'families' => $list, 'q' => $q ]);
    }

    #[Route('/famille/{id}', name: 'admin_family')]
    public function family(int $id, Request $request): Response
    {
        if ($resp = $this->assertAdmin2FA($request)) { return $resp; }
        $family = $this->em->getRepository(Family::class)->find($id);
        if (!$family) { $this->addFlash('error', 'Famille introuvable'); return $this->redirectToRoute('admin_home'); }
        return $this->render('administration/famille.html.twig', [ 'family' => $family ]);
    }

    #[Route('/messages', name: 'admin_messages')]
    public function messages(Request $request): Response
    {
        if ($resp = $this->assertAdmin2FA($request)) { return $resp; }
        $msgs = $this->em->getRepository(ContactMessage::class)->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC')->setMaxResults(200)->getQuery()->getResult();
        return $this->render('administration/messages.html.twig', ['messages' => $msgs]);
    }

    #[Route('/messages/{id}', name: 'admin_message_show')]
    public function messageShow(int $id, Request $request): Response
    {
        if ($resp = $this->assertAdmin2FA($request)) { return $resp; }
        $m = $this->em->getRepository(ContactMessage::class)->find($id);
        if (!$m) { $this->addFlash('error', 'Message introuvable'); return $this->redirectToRoute('admin_messages'); }
        return $this->render('administration/message_show.html.twig', ['m' => $m]);
    }

    #[Route('/messages/{id}/status', name: 'admin_message_status', methods: ['POST'])]
    public function messageStatus(int $id, Request $request): Response
    {
        if ($resp = $this->assertAdmin2FA($request)) { return $resp; }
        $m = $this->em->getRepository(ContactMessage::class)->find($id);
        if (!$m) { $this->addFlash('error', 'Message introuvable'); return $this->redirectToRoute('admin_messages'); }
        if (!$this->isCsrfTokenValid('admin_message_status_'.$id, (string)$request->request->get('_token'))) {
            $this->addFlash('error', 'CSRF invalide');
            return $this->redirectToRoute('admin_message_show', ['id' => $id]);
        }
        $status = (string)$request->request->get('status', 'new');
        $m->setStatus($status);
        $this->em->flush();
        $this->addFlash('success', 'Statut mis à jour');
        return $this->redirectToRoute('admin_message_show', ['id' => $id]);
    }

    #[Route('/messages/{id}/reply', name: 'admin_message_reply', methods: ['POST'])]
    public function messageReply(int $id, Request $request, MailerInterface $mailer): Response
    {
        if ($resp = $this->assertAdmin2FA($request)) { return $resp; }
        $m = $this->em->getRepository(ContactMessage::class)->find($id);
        if (!$m) { $this->addFlash('error', 'Message introuvable'); return $this->redirectToRoute('admin_messages'); }
        if (!$this->isCsrfTokenValid('admin_message_reply_'.$id, (string)$request->request->get('_token'))) {
            $this->addFlash('error', 'CSRF invalide');
            return $this->redirectToRoute('admin_message_show', ['id' => $id]);
        }
        $subject = trim((string)$request->request->get('subject', '')) ?: 'Réponse — Service modération TribuConnect';
        $body = trim((string)$request->request->get('reply', ''));
        if ($body === '') {
            $this->addFlash('error', 'Le message de réponse est vide.');
            return $this->redirectToRoute('admin_message_show', ['id' => $id]);
        }
        try {
            $email = (new Email())
                ->from('contact@tribuconnect.fr')
                ->to($m->getFromEmail())
                ->subject($subject)
                ->text($body."\n\n— Service modération TribuConnect");
            $mailer->send($email);
            if ($m->getStatus() === 'new') { $m->setStatus('in_progress'); $this->em->flush(); }
            $this->addFlash('success', 'Réponse envoyée.');
        } catch (\Throwable) {
            $this->addFlash('error', "Échec de l'envoi de la réponse.");
        }
        return $this->redirectToRoute('admin_message_show', ['id' => $id]);
    }

    #[Route('/famille/{id}/retirer-membre/{userId}', name: 'admin_family_remove_member', methods: ['POST'])]
    public function removeMember(int $id, int $userId, Request $request): Response
    {
        if ($resp = $this->assertAdmin2FA($request)) { return $resp; }
        $family = $this->em->getRepository(Family::class)->find($id);
        $user = $this->em->getRepository(User::class)->find($userId);
        if (!$family || !$user) { $this->addFlash('error', 'Introuvable'); return $this->redirectToRoute('admin_family', ['id' => $id]); }
        if (!$this->isCsrfTokenValid('admin_remove_member_'.$id.'_'.$userId, (string)$request->request->get('_token'))) {
            $this->addFlash('error', 'CSRF invalide');
            return $this->redirectToRoute('admin_family', ['id' => $id]);
        }
        $user->removeFamily($family);
        $this->em->flush();
        $this->addFlash('success', 'Membre retiré');
        return $this->redirectToRoute('admin_family', ['id' => $id]);
    }

    #[Route('/famille/{id}/supprimer-photo/{photoId}', name: 'admin_delete_photo', methods: ['POST'])]
    public function deletePhoto(int $id, int $photoId, Request $request): Response
    {
        if ($resp = $this->assertAdmin2FA($request)) { return $resp; }
        $photo = $this->em->getRepository(Photo::class)->find($photoId);
        if (!$photo) { $this->addFlash('error', 'Photo introuvable'); return $this->redirectToRoute('admin_family', ['id' => $id]); }
        if (!$this->isCsrfTokenValid('admin_delete_photo_'.$photoId, (string)$request->request->get('_token'))) {
            $this->addFlash('error', 'CSRF invalide');
            return $this->redirectToRoute('admin_family', ['id' => $id]);
        }
        $this->em->remove($photo);
        $this->em->flush();
        $this->addFlash('success', 'Photo supprimée');
        return $this->redirectToRoute('admin_family', ['id' => $id]);
    }

    #[Route('/famille/{id}/supprimer', name: 'admin_family_delete', methods: ['POST'])]
    public function deleteFamily(int $id, Request $request): Response
    {
        if ($resp = $this->assertAdmin2FA($request)) { return $resp; }
        $family = $this->em->getRepository(Family::class)->find($id);
        if (!$family) { $this->addFlash('error', 'Famille introuvable'); return $this->redirectToRoute('admin_home'); }
        if (!$this->isCsrfTokenValid('admin_delete_family_'.$id, (string)$request->request->get('_token'))) {
            $this->addFlash('error', 'CSRF invalide');
            return $this->redirectToRoute('admin_family', ['id' => $id]);
        }
        $this->em->remove($family);
        $this->em->flush();
        $this->addFlash('success', 'Famille supprimée');
        return $this->redirectToRoute('admin_home');
    }

    #[Route('/famille/{id}/changer-proprietaire', name: 'admin_family_change_owner', methods: ['POST'])]
    public function adminChangeOwner(int $id, Request $request): Response
    {
        if ($resp = $this->assertAdmin2FA($request)) { return $resp; }
        $family = $this->em->getRepository(Family::class)->find($id);
        if (!$family) { $this->addFlash('error', 'Famille introuvable'); return $this->redirectToRoute('admin_home'); }
        if (!$this->isCsrfTokenValid('admin_change_owner_'.$id, (string)$request->request->get('_token'))) {
            $this->addFlash('error', 'CSRF invalide');
            return $this->redirectToRoute('admin_family', ['id' => $id]);
        }
        $newId = (int)($request->request->get('new_owner_id') ?? 0);
        $new = $this->em->getRepository(User::class)->find($newId);
        if (!$new) { $this->addFlash('error', 'Utilisateur introuvable'); return $this->redirectToRoute('admin_family', ['id' => $id]); }
        $isMember = false; foreach ($family->getMembers() as $m) { if ($m->getId() === $new->getId()) { $isMember = true; break; } }
        if (!$isMember) { $this->addFlash('error', "L'utilisateur n'appartient pas à la famille"); return $this->redirectToRoute('admin_family', ['id' => $id]); }
        $family->setOwner($new);
        $this->em->flush();
        $this->addFlash('success', 'Propriétaire mis à jour');
        return $this->redirectToRoute('admin_family', ['id' => $id]);
    }

    #[Route('/double-identification', name: 'admin_2fa')]
    public function twoFactor(Request $request, MailerInterface $mailer): Response
    {
        $user = $this->getUser(); if (!$user) { return $this->redirectToRoute('app_login'); }
        // Send a new code on GET (if enabled)
        if (method_exists($user, 'isTwoFactorEnabled') && $user->isTwoFactorEnabled()) {
            // code aléatoire sur 5 chiffres
            $code = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            if (method_exists($user, 'setTwoFactorCode')) {
                $user->setTwoFactorCode($code);
                $user->setTwoFactorExpiresAt(new \DateTimeImmutable('+10 minutes'));
                $this->em->flush();
            }
            // envoi du code à l'adresse de contact générique
            try {
                $email = (new Email())
                    ->from('contact@tribuconnect.fr')
                    ->to('contact@tribuconnect.fr')
                    ->subject('Code de connexion admin (2FA)')
                    ->text('Code: '.$code);
                $mailer->send($email);
            } catch (\Throwable) { /* ignore en dev */ }
        }
        return $this->render('administration/double_identification.html.twig');
    }

    #[Route('/double-identification/verification', name: 'admin_2fa_verify', methods: ['POST'])]
    public function twoFactorVerify(Request $request): Response
    {
        $user = $this->getUser(); if (!$user) { return $this->redirectToRoute('app_login'); }
        $code = trim((string)$request->request->get('code', ''));
        $ok = false;
        if (method_exists($user, 'getTwoFactorCode') && method_exists($user, 'getTwoFactorExpiresAt')) {
            if ($user->getTwoFactorCode() && $user->getTwoFactorExpiresAt() && $user->getTwoFactorExpiresAt() > new \DateTimeImmutable()) {
                $ok = hash_equals($user->getTwoFactorCode(), $code);
            }
        }
        if ($ok) {
            $request->getSession()->set('admin_2fa_verified', true);
            $this->addFlash('success', 'Double identification validée.');
            return $this->redirectToRoute('admin_home');
        }
        $this->addFlash('error', 'Code invalide ou expiré.');
        return $this->redirectToRoute('admin_2fa');
    }

    #[Route('/double-identification/renvoyer', name: 'admin_2fa_resend', methods: ['POST'])]
    public function twoFactorResend(Request $request, MailerInterface $mailer): Response
    {
        $user = $this->getUser(); if (!$user) { return $this->redirectToRoute('app_login'); }
        if (!$this->isCsrfTokenValid('admin_2fa_resend', (string)$request->request->get('_token'))) {
            $this->addFlash('error', 'CSRF invalide.');
            return $this->redirectToRoute('admin_2fa');
        }
        // nouveau code sur 5 chiffres
        $code = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        if (method_exists($user, 'setTwoFactorCode')) {
            $user->setTwoFactorCode($code);
            $user->setTwoFactorExpiresAt(new \DateTimeImmutable('+10 minutes'));
            $this->em->flush();
        }
        // renvoi du code uniquement par email générique
        try {
            $email = (new Email())
                ->from('contact@tribuconnect.fr')
                ->to('contact@tribuconnect.fr')
                ->subject('Code de connexion admin (2FA)')
                ->text('Code: '.$code);
            $mailer->send($email);
        } catch (\Throwable) { }
        $this->addFlash('success', 'Un nouveau code vous a été envoyé.');
        return $this->redirectToRoute('admin_2fa');
    }
}
