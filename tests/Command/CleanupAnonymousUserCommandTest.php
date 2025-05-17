<?php

namespace App\Tests\Command;

use App\Entity\{BlogPost, ConversationDeletion, Message, User, Event};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CleanupAnonymousUserCommandTest extends KernelTestCase
{
    private $em;
    private $userRepository;
    private $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->userRepository = $this->em->getRepository(User::class);
        $command = self::getContainer()->get('App\Command\CleanupAnonymousUserCommand');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeQuery('DELETE FROM conversation_deletion');
        $conn->executeQuery('DELETE FROM message');
        $conn->executeQuery('DELETE FROM blog_post');
        $conn->executeQuery('DELETE FROM user');

        parent::tearDown();
        $this->em->clear();
    }

    public function testNoAnonymousUserExists(): void
    {
        $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Aucun utilisateur anonyme trouvé.', $output);
    }

    public function testAnonymousUserWithRemainingRelations(): void
    {
        $anonymousUser = $this->userRepository->findOrCreateAnonymousUser();

        $blogPost = (new BlogPost())
            ->setTitle('Titre de test')
            ->setContent('Contenu de test')
            ->setAuthor($anonymousUser);

        $this->em->persist($blogPost);
        $this->em->flush();

        $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('L\'utilisateur anonyme a encore des données associées', $output);
        $this->assertStringContainsString('- Relations actives : 1', $output);
        $this->assertNotNull($this->userRepository->findAnonymousUser());
    }

    public function testAnonymousUserDeletedWhenNoRelations(): void
    {
        $anonymousUser = $this->userRepository->findOrCreateAnonymousUser();
        $this->em->persist($anonymousUser);
        $this->em->flush();

        $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Utilisateur anonyme supprimé avec succès.', $output);
        $this->assertNull($this->userRepository->findAnonymousUser());
    }

    public function testOrphanMessagesCleanedUp(): void
    {
        $anonymousUser = $this->userRepository->findOrCreateAnonymousUser();
        $this->em->persist($anonymousUser);

        $message = (new Message())
            ->setTitle('Titre message')
            ->setSender($anonymousUser)
            ->setReceiver($anonymousUser)
            ->setContent('Test');
        $this->em->persist($message);

        $this->em->flush();

        $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Supprimé 1 messages orphelins', $output);
        $this->assertEmpty($this->em->getRepository(Message::class)->findAll());
    }

    public function testCheckAllRelationTypes(): void
    {
        $anonymousUser = $this->userRepository->findOrCreateAnonymousUser();
        $anonymousUser->setDeletedAt(null);

        $otherUser = (new User())
            ->setEmail('other@example.com')
            ->setPseudo('Other_' . bin2hex(random_bytes(4)))
            ->setPassword('password')
            ->setDeletedAt(null);

        $this->em->persist($otherUser);

        // Message
        $message = (new Message())
            ->setTitle('Titre message')
            ->setSender($anonymousUser)
            ->setReceiver($otherUser)
            ->setContent('Test');
        $this->em->persist($message);

        // BlogPost
        $blogPost = (new BlogPost())
            ->setTitle('Titre de test')
            ->setContent('Contenu de test')
            ->setAuthor($anonymousUser);
        $this->em->persist($blogPost);

        // ConversationDeletion
        $conv1 = (new ConversationDeletion())
            ->setUser($anonymousUser)
            ->setOtherUser($otherUser);
        $this->em->persist($conv1);

        $conv2 = (new ConversationDeletion())
            ->setUser($otherUser)
            ->setOtherUser($anonymousUser);
        $this->em->persist($conv2);

        $this->em->flush();

        $this->commandTester->execute([]);
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('- Relations actives : 4', $output);
    }
}
