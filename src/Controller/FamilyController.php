<?php

namespace App\Controller;

use App\Entity\Family;
use App\Entity\Invitation;
use App\Entity\Message;
use App\Form\FamilyType;
use App\Form\InvitationFormType;
use App\Form\PhotoFormType;
use App\Entity\Photo;
use App\Repository\PhotoRepository;
use App\Form\MessageFormType;
use App\Repository\EventRepository;
use App\Repository\InvitationRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\ByteString;

class FamilyController extends AbstractController
{
    public function index(Request $request, EntityManagerInterface $em, MailerInterface $mailer, EventRepository $events, PhotoRepository $photos, \App\Service\BirthdaySync $birthdaySync): Response
    {
        $user = $this->getUser();
        if (!$user) { return $this->redirectToRoute('app_login'); }

        $families = $user->getFamilies();
        // liste triée alphabétiquement (par nom) pour le choix par défaut
        $sortedFamilies = [];
        foreach ($families as $f) { $sortedFamilies[] = $f; }
        usort($sortedFamilies, function(Family $a, Family $b) {
            return strcasecmp((string)$a->getName(), (string)$b->getName());
        });

        $fid = (int)($request->query->get('fid') ?? 0);
        $family = null;
        if ($fid) {
            foreach ($families as $f) { if ($f->getId() === $fid) { $family = $f; break; } }
            // Admin: autoriser l'accès à n'importe quelle famille par id
            if (!$family && $this->isGranted('ROLE_ADMIN')) {
                $family = $em->getRepository(Family::class)->find($fid);
            }
        }
        if (!$family && count($sortedFamilies) > 0) { $family = $sortedFamilies[0]; }

        $familyForm = null;
        $inviteForm = null;
        $messageForm = null;
        $photoForm = null;
        $newFamilyForm = null;
        $chatPhotoCount = 0;
        $chatPhotoLimit = 50;
        $chatPhotoLimitReached = false;

        // Allow creating only if user does not already own a family
        $ownsOne = false; foreach ($families as $f) { if ($f->getOwner() && $f->getOwner()->getId() === $user->getId()) { $ownsOne = true; break; } }
        if ($this->isGranted('ROLE_ADMIN')) { $ownsOne = true; }
        if (!$family && !$ownsOne) {
            $family = new Family();
            $familyForm = $this->createForm(FamilyType::class, $family);
            $familyForm->handleRequest($request);
            if ($familyForm->isSubmitted() && $familyForm->isValid()) {
                $family->setOwner($user);
                $user->addFamily($family);
                $em->persist($family);
                $em->flush();
                $this->addFlash('success', 'Famille créée.');
                return $this->redirectToRoute('family_home', ['fid' => $family->getId()]);
            }
        } elseif ($family) {
            try {
                $chatPhotoCount = (int)$em->createQuery('SELECT COUNT(m.id) FROM App\\Entity\\Message m WHERE m.family = :fam AND m.imagePath IS NOT NULL')
                    ->setParameter('fam', $family)
                    ->getSingleScalarResult();
            } catch (\Throwable) { $chatPhotoCount = 0; }
            $chatPhotoLimitReached = $chatPhotoCount >= $chatPhotoLimit;
            // Invitation
            $invitation = new Invitation();
            $inviteForm = $this->createForm(InvitationFormType::class, $invitation);
            $inviteForm->handleRequest($request);
            if ($inviteForm->isSubmitted() && $inviteForm->isValid()) {
                $invitation->setFamily($family);
                $invitation->setToken(ByteString::fromRandom(32)->toString());
                $em->persist($invitation);
                $em->flush();

                $link = $this->generateUrl('family_invite_accept', ['token' => $invitation->getToken()], 0);
                $email = (new Email())
                    ->from($this->getParameter('app.mail_from'))
                    ->to($invitation->getEmail())
                    ->subject(sprintf('%s vous invite à rejoindre la famille %s', $user->getFirstName(), $family->getName()))
                    ->text("Cliquez pour accepter l'invitation: " . $link);
                try { $mailer->send($email); $this->addFlash('success', 'Invitation envoyée.'); }
                catch (\Throwable) { $this->addFlash('error', "Invitation enregistrée mais email non envoyée."); }

                return $this->redirectToRoute('family_home', ['fid' => $family->getId()]);
            }

            // Galerie: ajout photo
            $photoForm = $this->createForm(PhotoFormType::class);
            $photoForm->handleRequest($request);
            if ($photoForm->isSubmitted() && $photoForm->isValid()) {
                /** @var UploadedFile|null $img */
                $img = $photoForm->get('image')->getData();
                $caption = (string)$photoForm->get('caption')->getData();
                if ($img) {
                    $uploads = dirname(__DIR__, 2) . '/public/uploads/photos';
                    (new Filesystem())->mkdir($uploads);
                    $name = bin2hex(random_bytes(12)) . '.' . $img->guessExtension();
                    $img->move($uploads, $name);
                    $p = new Photo();
                    $p->setFamily($family);
                    $p->setAuthor($user);
                    $p->setPath('/uploads/photos/' . $name);
                    if ($caption) { $p->setCaption($caption); }
                    $em->persist($p);
                    $em->flush();
                    $this->addFlash('success', 'Photo ajoutée à la galerie.');
                    return $this->redirectToRoute('family_home', ['fid' => $family->getId()]);
                }
            }

            // Messages
            $message = new Message();
            $messageForm = $this->createForm(MessageFormType::class, $message);
            $messageForm->handleRequest($request);
            if ($messageForm->isSubmitted() && $messageForm->isValid()) {
                $message->setAuthor($user);
                $message->setFamily($family);
                /** @var UploadedFile|null $img */
                $img = $messageForm->get('image')->getData();
                if ($img) {
                    if ($chatPhotoLimitReached) {
                        $this->addFlash('error', 'La limite de 50 photos dans la messagerie est atteinte. Votre image n\'a pas été envoyée.');
                        $img = null; // ignore image, keep text message
                    }
                }
                if ($img) {
                    $uploads = dirname(__DIR__, 2) . '/public/uploads/chat';
                    (new Filesystem())->mkdir($uploads);
                    $name = bin2hex(random_bytes(8)) . '.' . $img->guessExtension();
                    $img->move($uploads, $name);
                    $message->setImagePath('/uploads/chat/' . $name);
                }
                $em->persist($message);
                $em->flush();
                try { (new \App\Service\BirthdaySync($em))->syncForUserInFamily($user, $new); } catch (\Throwable $e) {}
                try { (new \App\Service\BirthdaySync($em))->syncForUserInFamily($user, $family); } catch (\Throwable $e) {}
                try { $birthdaySync->syncForUserInFamily($user, $family); } catch (\Throwable $e) {}
                try { $birthdaySync->syncForUserInFamily($user, $new); } catch (\Throwable $e) {}
                try { $birthdaySync->syncForUserInFamily($user, $family); } catch (\Throwable $e) {}
                return $this->redirectToRoute('family_home', ['fid' => $family->getId()]);
            }

            // Events current month
            $year = (int)($request->query->get('year') ?: (new \DateTimeImmutable())->format('Y'));
            $month = (int)($request->query->get('month') ?: (new \DateTimeImmutable())->format('n'));
            $start = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
            $end = $start->modify('last day of this month 23:59:59');
            $qb = $events->createQueryBuilder('e')
                ->andWhere('IDENTITY(e.family) = :fid')
                ->andWhere('(e.startAt BETWEEN :start AND :end) OR (e.recurrence = :yearly)')
                ->setParameter('fid', (int)$family->getId())
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->setParameter('yearly', \App\Entity\Event::RECURRENCE_YEARLY)
                ->orderBy('e.startAt', 'ASC');
            $monthEvents = $qb->getQuery()->getResult();
        }

        // Allow users who are already in a family but aren't owners of any
        // to create their own family in addition
        if (!$ownsOne) {
            $new = new Family();
            $newFamilyForm = $this->createForm(FamilyType::class, $new, ['attr' => ['id' => 'new-family-form']]);
            $newFamilyForm->handleRequest($request);
            if ($newFamilyForm->isSubmitted() && $newFamilyForm->isValid()) {
                $new->setOwner($user);
                $user->addFamily($new);
                $em->persist($new);
                $em->flush();
                $this->addFlash('success', 'Nouvelle famille créée.');
                return $this->redirectToRoute('family_home', ['fid' => $new->getId()]);
            }
        }

        return $this->render('family/index.html.twig', [
            'family' => $family,
            'families' => $families,
            'familyForm' => $familyForm?->createView(),
            'newFamilyForm' => $newFamilyForm?->createView(),
            'inviteForm' => $inviteForm?->createView(),
            'messageForm' => $messageForm?->createView(),
            'photoForm' => $photoForm?->createView(),
            'gallery' => ($family ? $photos->findByFamily($family, 500) : []),
            'monthEvents' => $monthEvents ?? [],
            'currentMonth' => isset($start) ? $start : new \DateTimeImmutable(),
            'chatPhotoCount' => $chatPhotoCount,
            'chatPhotoLimit' => $chatPhotoLimit,
            'chatPhotoLimitReached' => $chatPhotoLimitReached,
        ]);
    }

    #[Route('/photo/{id}/delete', name: 'photo_delete', methods: ['POST'])]
    public function deletePhoto(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser(); if (!$user) { return $this->redirectToRoute('app_login'); }
        /** @var Photo|null $photo */
        $photo = $em->getRepository(Photo::class)->find($id);
        if (!$photo) { $this->addFlash('error', 'Photo introuvable.'); return $this->redirectToRoute('family_home'); }
        $family = $photo->getFamily();
        if (!$family) { $this->addFlash('error', 'Famille introuvable pour cette photo.'); return $this->redirectToRoute('family_home'); }

        $isOwner = $family->getOwner() && $family->getOwner()->getId() === $user->getId();
        $isAuthor = $photo->getAuthor() && $photo->getAuthor()->getId() === $user->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        if (!$isOwner && !$isAuthor && !$isAdmin) { $this->addFlash('error', 'Vous ne pouvez pas supprimer cette photo.'); return $this->redirectToRoute('family_home', ['fid' => $family->getId()]); }

        if (!$this->isCsrfTokenValid('delete_photo_'.$photo->getId(), (string)$request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('family_home', ['fid' => $family->getId()]);
        }

        $em->remove($photo);
        $em->flush();
        $this->addFlash('success', 'Photo supprimée.');
        return $this->redirectToRoute('family_home', ['fid' => $family->getId()]);
    }

    #[Route('/message/{id}/delete', name: 'message_delete', methods: ['POST'])]
    public function deleteMessage(int $id, Request $request, EntityManagerInterface $em, MessageRepository $messages): Response
    {
        $user = $this->getUser(); if (!$user) { return $this->redirectToRoute('app_login'); }
        $msg = $messages->find($id);
        if (!$msg) { $this->addFlash('error', 'Message introuvable.'); return $this->redirectToRoute('family_home'); }
        $family = $msg->getFamily();
        if (!$family) { $this->addFlash('error', 'Famille introuvable pour ce message.'); return $this->redirectToRoute('family_home'); }
        $isOwner = $family->getOwner() && $family->getOwner()->getId() === $user->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        if (!$isOwner && !$isAdmin) { $this->addFlash('error', 'Vous ne pouvez pas supprimer ce message.'); return $this->redirectToRoute('family_home', ['fid' => $family->getId()]); }
        if (!$this->isCsrfTokenValid('delete_message_'.$msg->getId(), (string)$request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('family_home', ['fid' => $family->getId()]);
        }
        $em->remove($msg);
        $em->flush();
        $this->addFlash('success', 'Message supprimé.');
        return $this->redirectToRoute('family_home', ['fid' => $family->getId()]);
    }

    #[Route('/family/{id}/change-owner', name: 'family_change_owner', methods: ['POST'])]
    public function changeOwner(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser(); if (!$user) { return $this->redirectToRoute('app_login'); }
        $family = $em->getRepository(Family::class)->find($id);
        if (!$family) { $this->addFlash('error', 'Famille introuvable.'); return $this->redirectToRoute('family_home'); }
        $isOwner = $family->getOwner() && $family->getOwner()->getId() === $user->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        if (!$isOwner && !$isAdmin) { $this->addFlash('error', 'Accès refusé.'); return $this->redirectToRoute('family_home', ['fid' => $family->getId()]); }
        if (!$this->isCsrfTokenValid('family_change_owner_'.$family->getId(), (string)$request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('family_home', ['fid' => $family->getId()]);
        }
        $newId = (int)($request->request->get('new_owner_id') ?? 0);
        if ($newId <= 0) { $this->addFlash('error', 'Sélection invalide.'); return $this->redirectToRoute('family_home', ['fid' => $family->getId()]); }
        $newOwner = $em->getRepository(\App\Entity\User::class)->find($newId);
        if (!$newOwner) { $this->addFlash('error', 'Utilisateur introuvable.'); return $this->redirectToRoute('family_home', ['fid' => $family->getId()]); }
        // doit être membre de la famille
        $isMember = false; foreach ($family->getMembers() as $m) { if ($m->getId() === $newOwner->getId()) { $isMember = true; break; } }
        if (!$isMember) { $this->addFlash('error', "L'utilisateur sélectionné ne fait pas partie de la famille."); return $this->redirectToRoute('family_home', ['fid' => $family->getId()]); }
        $family->setOwner($newOwner);
        $em->flush();
        $this->addFlash('success', 'Nouveau propriétaire défini.');
        return $this->redirectToRoute('family_home', ['fid' => $family->getId()]);
    }

    #[Route('/invite/{token}', name: 'family_invite_accept')]
    public function accept(string $token, InvitationRepository $invitations, EntityManagerInterface $em, \App\Service\BirthdaySync $birthdaySync): Response
    {
        $inv = $invitations->findOneBy(['token' => $token, 'status' => 'pending']);
        if (!$inv) {
            $this->addFlash('error', 'Invitation invalide ou expirée.');
            return $this->redirectToRoute('homepage');
        }
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('info', 'Créez un compte ou connectez-vous pour accepter.');
            return $this->redirectToRoute('app_register');
        }
        // allow multiple families
        $user->addFamily($inv->getFamily());
        $inv->setStatus('accepted');
        $inv->setAcceptedAt(new \DateTimeImmutable());
        $em->flush();
        try { $birthdaySync->syncForUserInFamily($user, $inv->getFamily()); } catch (\Throwable $e) {}
        $this->addFlash('success', 'Invitation acceptée. Bienvenue !');
        return $this->redirectToRoute('family_home');
    }

    #[Route('/family/{id}/add-member', name: 'family_add_member', methods: ['POST'])]
    public function addMember(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser(); if (!$user) { return $this->redirectToRoute('app_login'); }
        $family = $em->getRepository(Family::class)->find($id);
        if (!$family) { $this->addFlash('error', 'Famille introuvable.'); return $this->redirectToRoute('family_home'); }
        if (!$family->getOwner() || $family->getOwner()->getId() !== $user->getId()) { $this->addFlash('error', 'Accès refusé.'); return $this->redirectToRoute('family_home'); }
        if (!$this->isCsrfTokenValid('add_member_'.$family->getId(), (string)$request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.'); return $this->redirectToRoute('family_home', ['fid' => $family->getId()]);
        }
        $email = trim((string)$request->request->get('email'));
        if (!$email) { $this->addFlash('error', 'Email requis.'); return $this->redirectToRoute('family_home'); }
        $target = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => strtolower($email)]);
        if ($target) {
            $target->addFamily($family);
            $em->flush();
            try { (new \App\Service\BirthdaySync($em))->syncForUserInFamily($target, $family); } catch (\Throwable $e) {}
            $this->addFlash('success', 'Membre ajouté à la famille.');
        } else {
            $this->addFlash('info', "Utilisateur introuvable. Utilisez l'invitation par email.");
        }
        return $this->redirectToRoute('family_home');
    }

    #[Route('/family/{id}/delete', name: 'family_delete', methods: ['POST'])]
    public function deleteFamily(int $id, EntityManagerInterface $em, Request $request): Response
    {
        $user = $this->getUser(); if (!$user) { return $this->redirectToRoute('app_login'); }
        $family = $em->getRepository(Family::class)->find($id);
        if (!$family) { $this->addFlash('error', 'Famille introuvable.'); return $this->redirectToRoute('family_home'); }
        if (!$family->getOwner() || $family->getOwner()->getId() !== $user->getId()) { $this->addFlash('error', 'Accès refusé.'); return $this->redirectToRoute('family_home'); }
        if (!$this->isCsrfTokenValid('delete_family_'.$family->getId(), (string)$request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.'); return $this->redirectToRoute('family_home');
        }
        $em->remove($family);
        $em->flush();
        $this->addFlash('success', 'Famille supprimée.');
        return $this->redirectToRoute('family_home');
    }
}



