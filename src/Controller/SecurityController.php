<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    public function register(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, UserRepository $users): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existing = $users->findOneBy(['email' => $user->getEmail()]);
            if ($existing) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');
            } else {
                $user->setPassword($hasher->hashPassword($user, $user->getPassword()));
                $em->persist($user);
                $em->flush();
                $this->addFlash('success', 'Compte créé. Vous pouvez vous connecter.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    public function logout(): void
    {
        // Symfony intercepts this route
    }

    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password')]
    public function forgotPassword(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator
    ): Response {
        if ($request->isMethod('POST')) {
            $email = trim((string)$request->request->get('email', ''));
            if ($email !== '') {
                $user = $users->findOneBy(['email' => mb_strtolower($email)]);
                if ($user) {
                    $token = bin2hex(random_bytes(32));
                    $user->setResetPasswordToken($token);
                    $user->setResetPasswordExpiresAt(new \DateTimeImmutable('+1 hour'));
                    $em->flush();

                    $link = $urlGenerator->generate('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

                    try {
                        $mail = (new Email())
                            ->from('contact@tribuconnect.fr')
                            ->to($user->getEmail())
                            ->subject('Réinitialisation de votre mot de passe')
                            ->text(
                                "Bonjour,\n\n".
                                "Pour réinitialiser votre mot de passe TribuConnect, cliquez sur le lien suivant (valide 1 heure) :\n".
                                $link."\n\n".
                                "Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email."
                            );
                        $mailer->send($mail);
                    } catch (\Throwable $e) {
                        // en dev, on ignore les erreurs d'envoi
                    }
                }
            }
            $this->addFlash('success', 'Si un compte existe avec cet email, un lien de réinitialisation a été envoyé.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reinitialisation-mot-de-passe/{token}', name: 'app_reset_password')]
    public function resetPassword(
        string $token,
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $user = $users->findOneBy(['resetPasswordToken' => $token]);
        $now = new \DateTimeImmutable();
        if (!$user || !$user->getResetPasswordExpiresAt() || $user->getResetPasswordExpiresAt() < $now) {
            $this->addFlash('error', 'Lien de réinitialisation invalide ou expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $csrfToken = (string)$request->request->get('_token');
            if (!$this->isCsrfTokenValid('reset_password_'.$token, $csrfToken)) {
                $this->addFlash('error', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            $password = (string)$request->request->get('password', '');
            $confirm = (string)$request->request->get('password_confirm', '');

            if (strlen($password) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
            } elseif ($password !== $confirm) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            } else {
                $user->setPassword($hasher->hashPassword($user, $password));
                $user->setResetPasswordToken(null);
                $user->setResetPasswordExpiresAt(null);
                $em->flush();

                $this->addFlash('success', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
            'email' => $user->getEmail(),
        ]);
    }
}
