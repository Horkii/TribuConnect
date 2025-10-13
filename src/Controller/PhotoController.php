<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Form\PhotoUploadFormType;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PhotoController extends AbstractController
{
    #[Route('/photos', name: 'photos_home')]
    public function index(Request $request, EntityManagerInterface $em, EventRepository $events): Response
    {
        $user = $this->getUser();
        if (!$user || !$user->getFamily()) { return $this->redirectToRoute('family_home'); }

        $photo = new Photo();
        $form = $this->createForm(PhotoUploadFormType::class, $photo);

        // Limit the event choices to current family via form tweak
        $form->get('event')->setData(null);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('file')->getData();
            $event = $form->get('event')->getData();
            if (!$event || $event->getFamily()?->getId() !== $user->getFamily()->getId()) {
                $this->addFlash('error', 'Événement invalide.');
                return $this->redirectToRoute('photos_home');
            }

            $uploads = dirname(__DIR__, 2) . '/public/uploads/photos';
            (new Filesystem())->mkdir($uploads);
            $safeName = bin2hex(random_bytes(10)) . '.' . $file->guessExtension();
            $file->move($uploads, $safeName);

            $photo->setPath('/uploads/photos/' . $safeName);
            $photo->setUploadedBy($user);
            $photo->setEvent($event);
            $em->persist($photo);
            $em->flush();
            $this->addFlash('success', 'Photo téléversée.');
            return $this->redirectToRoute('photos_home');
        }

        // Photos of current family (simple query via event->family)
        $conn = $em->getConnection();
        $sql = 'SELECT p.* FROM photos p JOIN events e ON p.event_id = e.id WHERE e.family_id = :fid ORDER BY p.created_at DESC';
        try { $rows = $conn->executeQuery($sql, ['fid' => $user->getFamily()->getId()])->fetchAllAssociative(); }
        catch (\Throwable) { $rows = []; }

        return $this->render('photos/index.html.twig', [
            'uploadForm' => $form->createView(),
            'photos' => $rows,
        ]);
    }
}

