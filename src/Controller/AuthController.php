<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

#[Route('/auth', name: 'app_auth_')]
class AuthController extends AbstractController
{
    #[Route('/inscription', name: 'register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {

        // Redirige les utilisateurs déjà connectés
        if ($this->getUser()) {
            return $this->redirectToRoute('app_page_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Hash du mot de passe
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Enregistrement en base
            $entityManager->persist($user);
            $entityManager->flush();

            // Envoi d'email de confirmation
            $email = (new TemplatedEmail())
                ->from(new Address($this->getParameter('app.mailer_from'),$this->getParameter('app.mailer_from_name')))
                ->to(new Address($user->getEmail(), $user->getPseudo()))
                ->subject('Confirmez votre compte')
                ->htmlTemplate('emails/confirmation.html.twig')
                ->context([
                    'user' => $user,
                    'token' => $user->getRegistrationToken(),
                    'expiration_date' => new \DateTimeImmutable('+24 hours')
                ]);

            $mailer->send($email);

            $this->addFlash('success', 'Un email de confirmation a été envoyé à votre adresse.');
            return $this->redirectToRoute('app_page_home');
        }

        return $this->render('auth/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/confirmation/{token}', name: 'confirm_email')]
    public function confirmEmail(string $token, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->findOneBy(['registration_token' => $token]);

        if (!$user) {
            $this->addFlash('error', 'Token invalide ou expiré.');
            return $this->redirectToRoute('app_page_home');
        }

        // Vérification de la validité du token (24h)
        if ($user->isTokenExpired()) {
            $entityManager->remove($user);
            $entityManager->flush();
            
            $this->addFlash('error', 'Le lien de confirmation a expiré. Veuillez vous réinscrire.');
            return $this->redirectToRoute('app_auth_register');
        }

        // Activation du compte
        $user->setIsVerified(true);
        $user->setRegistrationToken(null);
        $user->setTokenExpiresAt(null);
        $entityManager->flush();

        $this->addFlash('success', 'Votre compte a été activé avec succès !');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/renvoyer-confirmation', name: 'resend_confirmation')]
    public function resendConfirmation(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($user && !$user->isVerified()) {
                // Régénération du token si nécessaire
                if (!$user->getRegistrationToken()) {
                    $user->setRegistrationToken(bin2hex(random_bytes(32)));
                    $user->setTokenExpiresAt(new \DateTimeImmutable('+24 hours'));
                    $entityManager->flush();
                }

                // Renvoi de l'email
                $email = (new TemplatedEmail())
                    ->from(new Address(
                        $this->getParameter('app.mailer_from'),
                        $this->getParameter('app.mailer_from_name')
                    ))
                    ->to($user->getEmail())
                    ->subject('Confirmez votre compte')
                    ->htmlTemplate('emails/confirmation.html.twig')
                    ->context([
                        'user' => $user,
                        'token' => $user->getRegistrationToken(),
                        'expiration_date' => new \DateTimeImmutable('+24 hours')
                    ]);

                $mailer->send($email);

                $this->addFlash('success', 'Un nouvel email de confirmation a été envoyé.');
                return $this->redirectToRoute('app_page_home');
            }

            $this->addFlash('error', 'Aucun compte non vérifié trouvé avec cette adresse email.');
        }

        return $this->render('auth/resend_confirmation.html.twig');
    }
}
