<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class AuthControllerTest extends WebTestCase
{
    public function testRegistrationWorkflow(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $router = $client->getContainer()->get('router');

        // Test d'accès à la page d'inscription
        $crawler = $client->request('GET', '/auth/inscription');
        $this->assertResponseIsSuccessful();

        // Données de test
        $uniqueId = uniqid();
        $formData = [
            'registration_form[pseudo]' => 'TestUser_' . $uniqueId,
            'registration_form[email]' => 'test_' . $uniqueId . '@example.com',
            'registration_form[plainPassword][first]' => 'SecurePass123!',
            'registration_form[plainPassword][second]' => 'SecurePass123!',
            'registration_form[agreeTerms]' => true
        ];

        // Soumission du formulaire
        $client->submitForm('S\'inscrire', $formData);
        $this->assertResponseRedirects('/');

        // Vérification utilisateur
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $formData['registration_form[email]']]);
        $this->assertNotNull($user);
        $this->assertFalse($user->isVerified());

        // Nettoyage
        $entityManager->remove($user);
        $entityManager->flush();
    }

    public function testEmailConfirmation(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = $client->getContainer()->get('security.password_hasher');
        $router = $client->getContainer()->get('router');

        // Création utilisateur
        $user = new User();
        $user->setPseudo('TestUser_' . uniqid())
            ->setEmail('test_' . uniqid() . '@example.com')
            ->setPassword($passwordHasher->hashPassword($user, 'SecurePass123!'))
            ->setRegistrationToken($token = bin2hex(random_bytes(32)))
            ->setTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        $entityManager->persist($user);
        $entityManager->flush();

        // Test confirmation
        $client->request('GET', "/auth/confirmation/{$token}");
        $this->assertResponseRedirects($router->generate('app_login'));

        // Vérification
        $entityManager->refresh($user);
        $this->assertTrue($user->isVerified());

        // Nettoyage
        $entityManager->remove($user);
        $entityManager->flush();
    }

    public function testPasswordResetWorkflow(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = $client->getContainer()->get('security.password_hasher');
        $router = $client->getContainer()->get('router');

        // Création utilisateur
        $user = new User();
        $user->setPseudo('TestUser_' . uniqid())
            ->setEmail('test_' . uniqid() . '@example.com')
            ->setPassword($passwordHasher->hashPassword($user, 'OldPass123!'))
            ->setIsVerified(true);

        $entityManager->persist($user);
        $entityManager->flush();

        // Récupération de l'ID avant détachement
        $userId = $user->getId();

        // Demande réinitialisation
        $client->request('GET', '/auth/mot-de-passe-oublie');
        $client->submitForm('Envoyer', ['email' => $user->getEmail()]);
        $this->assertResponseRedirects($router->generate('app_login'));

        // Rechargement de l'utilisateur depuis la base
        $user = $entityManager->getRepository(User::class)->find($userId);
        $token = $user->getResetToken();

        // Réinitialisation
        $client->request('GET', "/auth/reinitialiser-mot-de-passe/{$token}");
        $client->submitForm('Réinitialiser', [
            'new_password_form[plainPassword][first]' => 'NewPass123!',
            'new_password_form[plainPassword][second]' => 'NewPass123!'
        ]);
        $this->assertResponseRedirects($router->generate('app_login'));

        // Rechargement final pour vérification
        $entityManager->clear();
        $user = $entityManager->getRepository(User::class)->find($userId);
        $this->assertTrue($passwordHasher->isPasswordValid($user, 'NewPass123!'));

        // Nettoyage
        $entityManager->remove($user);
        $entityManager->flush();
    }
}
