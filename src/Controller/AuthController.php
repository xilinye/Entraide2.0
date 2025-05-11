<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
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


            $pseudo = $user->getPseudo();
            $initial = strtoupper(substr($pseudo, 0, 1));

            $fontSize = 90;
            $imageWidth = 200;
            $imageHeight = 200;
           
            // Couleurs de fond aléatoires
            $colors = [
                [52, 152, 219],  // Bleu
                [46, 204, 113],  // Vert
                [231, 76, 60],   // Rouge
                [155, 89, 182],  // Violet
                [250, 250, 15],  // Jaune
                [43, 255, 240],  // cyan
                [255, 70, 120],  // rose
                [255, 100, 0],   // orange
            ];
            $bgColor = $colors[array_rand($colors)];
            $image = imagecreatetruecolor($imageWidth, $imageHeight);

            $background = imagecolorallocate($image, $bgColor[0], $bgColor[1], $bgColor[2]);
            imagefilledrectangle($image, 0, 0, $imageWidth, $imageHeight, $background);
            
            // Définition la couleur du texte 
            $textColor = imagecolorallocate($image, 255, 255, 255);
            
            $fontPath = $this->getParameter('kernel.project_dir') . '/public/fonts/OpenSans-Bold.ttf';
            
            // Place la lettre au centre
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $initial);
            $textWidth = abs($bbox[2] - $bbox[0]);
            $textHeight = abs($bbox[7] - $bbox[1]);
            $x = ($imageWidth / 2) - ($textWidth / 2) - $bbox[0];
            $y = ($imageHeight / 2) + ($textHeight / 2);
            imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontPath, $initial);
            
            // Enregistrement de l'image dans uplaods
            $filename = md5(uniqid()) . '.png';
            $uploadPath = $this->getParameter('kernel.project_dir') . '/public/uploads/profile/' . $filename;
            imagepng($image, $uploadPath);
            imagedestroy($image);
            
            $user->setProfileImage($filename);
            
    
            // Génération du token de confirmation
            $user->setRegistrationToken(bin2hex(random_bytes(32)));
            $user->setTokenExpiresAt(new \DateTimeImmutable('+24 hours'));

            // Enregistrement en base
            $entityManager->persist($user);
            $entityManager->flush();

            // Envoi d'email de confirmation
            $email = (new TemplatedEmail())
                ->from(new Address($this->getParameter('app.mailer_from'), $this->getParameter('app.mailer_from_name')))
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
        $user = $entityManager->getRepository(User::class)->findOneBy(['registrationToken' => $token]);

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

    #[Route('/mot-de-passe-oublie', name: 'forgot_password_request')]
    public function forgotPasswordRequest(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($user && $user->isVerified()) {
                // Génération du token
                $user->setResetToken(bin2hex(random_bytes(32)));
                $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
                $entityManager->flush();

                // Envoi d'email
                $email = (new TemplatedEmail())
                    ->from(new Address(
                        $this->getParameter('app.mailer_from'),
                        $this->getParameter('app.mailer_from_name')
                    ))
                    ->to($user->getEmail())
                    ->subject('Réinitialisation de votre mot de passe')
                    ->htmlTemplate('emails/reset_password.html.twig')
                    ->context([
                        'user' => $user,
                        'token' => $user->getResetToken(),
                        'expiration_date' => new \DateTimeImmutable('+1 hour')
                    ]);

                $mailer->send($email);

                $this->addFlash('success', 'Un email de réinitialisation a été envoyé.');
                return $this->redirectToRoute('app_login');
            }

            $this->addFlash('error', 'Aucun compte vérifié trouvé avec cette adresse email.');
        }

        return $this->render('auth/forgot_password_request.html.twig');
    }

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'reset_password')]
    public function resetPassword(
        string $token,
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $entityManager->getRepository(User::class)->findOneBy(['resetToken' => $token]);

        if (!$user || $user->isResetTokenExpired()) {
            $this->addFlash('error', 'Lien invalide ou expiré');
            return $this->redirectToRoute('app_auth_forgot_password_request');
        }

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');

            // Validation du mot de passe
            $user->setPassword(
                $passwordHasher->hashPassword($user, $newPassword)
            );
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès !');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/reset_password.html.twig', [
            'token' => $token
        ]);
    }
}
