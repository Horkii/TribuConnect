<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ContactController extends AbstractController
{
    public function index(Request $request, MailerInterface $mailer, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('contact_form', (string)$request->request->get('_token'))) {
                $this->addFlash('error', 'Session expirée. Veuillez réessayer.');
                return $this->redirectToRoute('contact');
            }
            if (trim((string)$request->request->get('website')) !== '') {
                // Honeypot: ignorer silencieusement
                return $this->redirectToRoute('contact');
            }
            $from = (string)$request->request->get('email', 'noreply@example.test');
            $body = (string)$request->request->get('message', '');
            // Enregistrer pour modération
            $msg = new \App\Entity\ContactMessage();
            $msg->setFromEmail($from)->setBody($body);
            $em->persist($msg);
            $em->flush();
            $email = (new Email())
                ->from($from)
                ->to('support@tribuconnect.fr')
                ->subject('[TribuConnect] Message de contact')
                ->text($body);
            try {
                $mailer->send($email);
                // Accusé de réception automatique à l'expéditeur
                try {
                    $ack = (new Email())
                        ->from('support@tribuconnect.fr')
                        ->to($from)
                        ->subject('Accusé de réception — Service modération TribuConnect')
                        ->text(
                            "Bonjour,\n\n".
                            "Nous avons bien reçu votre message adressé au service modération de TribuConnect.\n".
                            "Notre équipe va étudier votre demande et vous répondre dans les meilleurs délais.\n\n".
                            "— L’équipe TribuConnect\n"
                        );
                    $mailer->send($ack);
                } catch (\Throwable) { /* ignorer l'échec d'auto-réponse */ }
                $this->addFlash('success', 'Message envoyé.');
            }
            catch (\Throwable) { $this->addFlash('error', "Impossible d'envoyer le message maintenant."); }
        }

        return $this->render('contact/index.html.twig');
    }
}
