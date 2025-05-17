<?php

namespace App\Tests\Command;

use App\Command\CleanResetTokensCommand;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CleanResetTokensCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
    public function testNoExpiredTokens(): void
    {
        // Create a user with a future token
        $user = (new User())
            ->setEmail('user@example.com')
            ->setPseudo('user_pseudo')
            ->setPassword('password')
            ->setResetToken('valid_token')
            ->setResetTokenExpiresAt(new \DateTimeImmutable('+1 day'));

        $this->em->persist($user);
        $this->em->flush();

        $commandTester = new CommandTester($this->getCommand());
        $commandTester->execute([]);

        // Assert output and status
        $this->assertStringContainsString('0 tokens expirés supprimés', $commandTester->getDisplay());
        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());

        // Verify the user wasn't modified
        $this->em->clear();
        $persistedUser = $this->em->find(User::class, $user->getId());
        $this->assertSame('valid_token', $persistedUser->getResetToken());
        $this->assertNotNull($persistedUser->getResetTokenExpiresAt());
    }
    public function testCleanExpiredTokens(): void
    {
        // Expired user
        $expiredUser = (new User())
            ->setEmail('expired@example.com')
            ->setPseudo('expired_pseudo')
            ->setPassword('password')
            ->setResetToken('expired_token')
            ->setResetTokenExpiresAt(new \DateTimeImmutable('-1 hour'));

        // Another expired user
        $anotherExpiredUser = (new User())
            ->setEmail('another_expired@example.com')
            ->setPseudo('another_expired_pseudo')
            ->setPassword('password')
            ->setResetToken('another_expired_token')
            ->setResetTokenExpiresAt(new \DateTimeImmutable('-1 day'));

        $this->em->persist($expiredUser);
        $this->em->persist($anotherExpiredUser);
        $this->em->flush();

        $commandTester = new CommandTester($this->getCommand());
        $commandTester->execute([]);

        // Assertions
        $this->assertStringContainsString('2 tokens expirés supprimés', $commandTester->getDisplay());
        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());

        $this->em->clear();
        $this->assertNull($this->em->find(User::class, $expiredUser->getId())->getResetToken());
        $this->assertNull($this->em->find(User::class, $anotherExpiredUser->getId())->getResetToken());
    }
    public function testUsersWithValidTokensUnaffected(): void
    {
        $validUser = (new User())
            ->setEmail('valid@example.com')
            ->setPseudo('valid_pseudo')
            ->setPassword('password')
            ->setResetToken('valid_token')
            ->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        $this->em->persist($validUser);
        $this->em->flush();

        $commandTester = new CommandTester($this->getCommand());
        $commandTester->execute([]);

        $this->assertStringContainsString('0 tokens expirés supprimés', $commandTester->getDisplay());

        $this->em->clear();
        $persistedUser = $this->em->find(User::class, $validUser->getId());
        $this->assertSame('valid_token', $persistedUser->getResetToken());
        $this->assertNotNull($persistedUser->getResetTokenExpiresAt());
    }
    public function testUsersWithoutTokensAreIgnored(): void
    {
        $userWithoutToken = (new User())
            ->setEmail('no_token@example.com')
            ->setPseudo('no_token_pseudo')
            ->setPassword('password');

        $this->em->persist($userWithoutToken);
        $this->em->flush();

        $commandTester = new CommandTester($this->getCommand());
        $commandTester->execute([]);

        $this->assertStringContainsString('0 tokens expirés supprimés', $commandTester->getDisplay());

        $this->em->clear();
        $persistedUser = $this->em->find(User::class, $userWithoutToken->getId());
        $this->assertNull($persistedUser->getResetToken());
        $this->assertNull($persistedUser->getResetTokenExpiresAt());
    }
    public function testUserWithExpiredExpiryButNullToken(): void
    {
        $user = (new User())
            ->setEmail('edge_case@example.com')
            ->setPseudo('edge_case_pseudo')
            ->setPassword('password')
            ->setResetToken(null)
            ->setResetTokenExpiresAt(new \DateTimeImmutable('-1 day'));

        $this->em->persist($user);
        $this->em->flush();

        $commandTester = new CommandTester($this->getCommand());
        $commandTester->execute([]);

        $this->assertStringContainsString('1 tokens expirés supprimés', $commandTester->getDisplay());

        $this->em->clear();
        $persistedUser = $this->em->find(User::class, $user->getId());
        $this->assertNull($persistedUser->getResetTokenExpiresAt());
    }

    private function getCommand(): CleanResetTokensCommand
    {
        return self::getContainer()->get(CleanResetTokensCommand::class);
    }
}
