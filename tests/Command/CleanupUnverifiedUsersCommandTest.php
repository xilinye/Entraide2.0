<?php

namespace App\Tests\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CleanupUnverifiedUsersCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserRepository $userRepository;
    private Application $application;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();

        // Réinitialisation complète de la base
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $this->em->getConnection()->executeStatement('TRUNCATE TABLE user');
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $this->userRepository = $this->em->getRepository(User::class);
        $this->application = new Application(self::$kernel);
    }

    public function testCommandWithNoUnverifiedUsers()
    {
        // Création d'utilisateurs vérifiés (ne doivent pas être supprimés)
        $this->createUser(true, new \DateTimeImmutable('-2 days'));
        $this->createUser(true, new \DateTimeImmutable('-1 hour'));

        $command = $this->application->find('app:cleanup-unverified-users');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertStringContainsString('Supprimé 0 compte(s) expiré(s)', $commandTester->getDisplay());
        $this->assertCount(2, $this->userRepository->findAll());
    }

    public function testCommandWithExpiredUnverifiedUsers()
    {
        // Création d'utilisateurs non vérifiés expirés
        $this->createUser(false, new \DateTimeImmutable('-25 hours'));
        $this->createUser(false, new \DateTimeImmutable('-2 days'));

        // Un utilisateur non vérifié récent
        $this->createUser(false, new \DateTimeImmutable('-23 hours'));

        $command = $this->application->find('app:cleanup-unverified-users');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertStringContainsString('Supprimé 2 compte(s) expiré(s)', $commandTester->getDisplay());
        $this->assertCount(1, $this->userRepository->findAll());
    }

    public function testCommandWithMixedUsers()
    {
        // Création de différents types d'utilisateurs
        $this->createUser(true, new \DateTimeImmutable('-3 days')); // Vérifié
        $this->createUser(false, new \DateTimeImmutable('-1 hour')); // Non vérifié récent
        $this->createUser(false, new \DateTimeImmutable('-26 hours')); // Non vérifié expiré

        $command = $this->application->find('app:cleanup-unverified-users');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertStringContainsString('Supprimé 1 compte(s) expiré(s)', $commandTester->getDisplay());
        $this->assertCount(2, $this->userRepository->findAll());
    }

    private function createUser(
        bool $isVerified,
        \DateTimeInterface $createdAt,
        ?\DateTimeInterface $tokenExpiresAt = null
    ): User {
        $user = new User();
        $user->setEmail(uniqid('test') . '@example.com') // Garantie d'unicité
            ->setPassword(password_hash('password', PASSWORD_DEFAULT))
            ->setIsVerified($isVerified)
            ->setPseudo(uniqid('TestUser_'));

        // Définition des dates via réflexion
        $reflector = new \ReflectionClass($user);

        $createdAtProp = $reflector->getProperty('createdAt');
        $createdAtProp->setAccessible(true);
        $createdAtProp->setValue($user, $createdAt);

        if ($tokenExpiresAt || !$isVerified) {
            $tokenExpiresAtProp = $reflector->getProperty('tokenExpiresAt');
            $tokenExpiresAtProp->setAccessible(true);
            $tokenExpiresAtProp->setValue(
                $user,
                $tokenExpiresAt ?? \DateTimeImmutable::createFromInterface($createdAt)
                    ->add(new \DateInterval('PT24H'))
            );
        }

        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear(); // Nettoyage du cache

        return $user;
    }

    public function testCommandWithExactExpirationTime()
    {
        $now = new \DateTimeImmutable();
        $this->createUser(
            false,
            $now->modify('-24 hours'),
            $now->modify('-1 second')
        );

        $command = $this->application->find('app:cleanup-unverified-users');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertStringContainsString('Supprimé 1 compte(s) expiré(s)', $commandTester->getDisplay());
        $this->assertCount(0, $this->userRepository->findAll());
    }

    public function testCommandWithMissingTokenExpiration()
    {
        // Création de l'utilisateur (détaché à cause du clear())
        $user = $this->createUser(false, new \DateTimeImmutable('-2 days'));

        // Référencement de l'entité managée
        $managedUser = $this->userRepository->find($user->getId());

        // Modification de la propriété
        $managedUser->setTokenExpiresAt(null);
        $this->em->flush();

        // Exécution de la commande
        $command = $this->application->find('app:cleanup-unverified-users');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        // Vérifications
        $this->assertStringContainsString('Supprimé 0 compte(s) expiré(s)', $commandTester->getDisplay());
        $this->assertCount(1, $this->userRepository->findAll());
    }

    public function testRepositoryQueryLogic()
    {
        // Test direct de la requête du repository
        $this->createUser(false, new \DateTimeImmutable(), new \DateTimeImmutable('-1 hour')); // Expiré
        $this->createUser(false, new \DateTimeImmutable(), new \DateTimeImmutable('+1 hour')); // Non expiré

        $expiredUsers = $this->userRepository->findExpiredUnverifiedUsers();

        $this->assertCount(1, $expiredUsers);
        $this->assertTrue($expiredUsers[0]->getTokenExpiresAt() < new \DateTimeImmutable());
    }

    protected function tearDown(): void
    {
        $this->em->clear();
        parent::tearDown();
    }
}
