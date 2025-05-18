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
        $this->em->getConnection()->beginTransaction();
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
        $user->setEmail(uniqid() . '@test.com')
            ->setPassword('password')
            ->setIsVerified($isVerified)
            ->setPseudo('TestUser_' . uniqid());

        // Définition de la date de création
        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('createdAt');
        $property->setAccessible(true);
        $property->setValue($user, $createdAt);

        // Gestion explicite de tokenExpiresAt
        if ($tokenExpiresAt !== null) {
            $user->setTokenExpiresAt($tokenExpiresAt);
        } elseif (!$isVerified) {
            // Comportement par défaut seulement si non vérifié et non spécifié
            $user->setTokenExpiresAt(
                \DateTimeImmutable::createFromInterface($createdAt)
                    ->add(new \DateInterval('PT24H'))
            );
        }

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function testCommandWithExactExpirationTime()
    {
        $expirationTime = (new \DateTimeImmutable())->modify('-1 second');

        $this->createUser(
            false,
            new \DateTimeImmutable('-24 hours'),
            $expirationTime
        );

        $command = $this->application->find('app:cleanup-unverified-users');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertStringContainsString('Supprimé 1 compte(s) expiré(s)', $commandTester->getDisplay());
        $this->assertCount(0, $this->userRepository->findAll());
    }

    public function testCommandWithMissingTokenExpiration()
    {
        // Utilisateur non vérifié sans tokenExpiresAt (simule un cas d'erreur)
        $user = $this->createUser(false, new \DateTimeImmutable('-2 days'));
        $user->setTokenExpiresAt(null);
        $this->em->flush();

        $command = $this->application->find('app:cleanup-unverified-users');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

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
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollback();
        }
        $this->em->clear();
        parent::tearDown();
    }
}
