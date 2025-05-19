<?php

namespace App\Tests\Command;

use App\Command\PromoteAdminCommand;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserManager;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use RuntimeException;

class PromoteAdminCommandTest extends TestCase
{
    public function testExecuteSuccessfullyPromotesUserToAdmin(): void
    {
        // Create a mock User
        $user = new User();
        $user->setEmail('test@example.com');

        // Mock UserRepository to return the user
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $userRepositoryMock->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($user);

        // Mock EntityManager to return the UserRepository
        /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $entityManagerMock */
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($userRepositoryMock);

        // Mock UserManager to check promoteToAdmin is called
        /** @var UserManager&\PHPUnit\Framework\MockObject\MockObject $userManagerMock */
        $userManagerMock = $this->createMock(UserManager::class);
        $userManagerMock->expects($this->once())
            ->method('promoteToAdmin')
            ->with($user);

        // Set up the command
        $command = new PromoteAdminCommand($entityManagerMock, $userManagerMock);
        $commandTester = new CommandTester($command);

        // Execute the command
        $commandTester->execute([
            'email' => 'test@example.com',
        ]);

        // Verify the output and status code
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Administrateur créé avec succès', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testExecuteUserNotFound(): void
    {
        // Mock UserRepository to return null
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $userRepositoryMock->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'notfound@example.com'])
            ->willReturn(null);

        // Mock EntityManager
        /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $entityManagerMock */
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($userRepositoryMock);

        // Mock UserManager (should not be called)
        /** @var UserManager&\PHPUnit\Framework\MockObject\MockObject $userManagerMock */
        $userManagerMock = $this->createMock(UserManager::class);
        $userManagerMock->expects($this->never())
            ->method('promoteToAdmin');

        // Set up the command
        $command = new PromoteAdminCommand($entityManagerMock, $userManagerMock);
        $commandTester = new CommandTester($command);

        // Execute the command
        $commandTester->execute([
            'email' => 'notfound@example.com',
        ]);

        // Verify the output and status code
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Utilisateur non trouvé', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testExecutePromotionFailure(): void
    {
        // Create a User instance
        $user = new User();
        $user->setEmail('test@example.com');

        // Mock UserRepository to return the user
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $userRepositoryMock->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($user);

        // Mock EntityManager to return the UserRepository
        /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $entityManagerMock */
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($userRepositoryMock);

        // Mock UserManager to throw an exception when promoting
        /** @var UserManager&\PHPUnit\Framework\MockObject\MockObject $userManagerMock */
        $userManagerMock = $this->createMock(UserManager::class);
        $userManagerMock->expects($this->once())
            ->method('promoteToAdmin')
            ->with($user)
            ->willThrowException(new RuntimeException('Erreur de promotion'));

        // Set up the command
        $command = new PromoteAdminCommand($entityManagerMock, $userManagerMock);
        $commandTester = new CommandTester($command);

        // Execute the command
        $commandTester->execute(['email' => 'test@example.com']);

        // Assert the error message and status code
        $this->assertStringContainsString('Erreur de promotion', $commandTester->getDisplay());
        $this->assertEquals(1, $commandTester->getStatusCode());
    }
}
