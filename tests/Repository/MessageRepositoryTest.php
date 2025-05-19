<?php

namespace App\Tests\Repository;

use App\Entity\{ConversationDeletion, Message, User};
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class MessageRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MessageRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = $this->em->getRepository(Message::class);
        $this->em->getConnection()->beginTransaction(); // Début de transaction
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollback(); // Annulation des changements
        }
        parent::tearDown();
        $this->em->close();
    }

    public function testFindConversations()
    {
        // Création des utilisateurs
        $user1 = (new User())->setPseudo('user1' . uniqid())->setEmail(uniqid('u1') . '@test.com')->setPassword('pass');
        $user2 = (new User())->setPseudo('user2' . uniqid())->setEmail(uniqid('u2') . '@test.com')->setPassword('pass');
        $this->em->persist($user1);
        $this->em->persist($user2);

        // Création des messages
        $message1 = (new Message())
            ->setContent('Hello')
            ->setTitle('Conversation 1')
            ->setSender($user1)
            ->setReceiver($user2);
        $message2 = (new Message())
            ->setContent('Hi')
            ->setTitle('Conversation 1')
            ->setSender($user2)
            ->setReceiver($user1)
            ->setIsRead(true);
        $message3 = (new Message())
            ->setContent('New message')
            ->setTitle('Conversation 1')
            ->setSender($user2)
            ->setReceiver($user1)
            ->setIsRead(false);
        $this->em->persist($message1);
        $this->em->persist($message2);
        $this->em->persist($message3);

        $this->em->flush();

        // Exécution de la méthode
        $conversations = $this->repository->findConversations($user1);

        // Assertions
        $this->assertCount(1, $conversations);
        $conv = $conversations[0];
        $this->assertEquals($user2->getPseudo(), $conv['other_user_pseudo']);
        $this->assertEquals(1, $conv['unread_count']);
    }

    public function testFindConversationBetweenUsers()
    {
        // Création des utilisateurs
        $user1 = (new User())->setPseudo('userA' . uniqid())->setEmail('a' . uniqid() . '@test.com')->setPassword('pass');
        $user2 = (new User())->setPseudo('userB' . uniqid())->setEmail('b' . uniqid() . '@test.com')->setPassword('pass');
        $this->em->persist($user1);
        $this->em->persist($user2);

        // Message 1 : Non supprimé (créé APRÈS la suppression)
        $message1 = (new Message())
            ->setContent("Message récent")
            ->setTitle("Conversation 1")
            ->setSender($user1)
            ->setReceiver($user2)
            ->setCreatedAt(new \DateTimeImmutable('2025-03-01'));

        // Message 2 : Supprimé (créé AVANT la suppression)
        $message2 = (new Message())
            ->setContent("Message ancien")
            ->setTitle("Conversation 1")
            ->setSender($user2)
            ->setReceiver($user1)
            ->setCreatedAt(new \DateTimeImmutable('2025-01-01'));

        $this->em->persist($message1);
        $this->em->persist($message2);

        $deletion = (new ConversationDeletion())
            ->setUser($user1)
            ->setOtherUser($user2)
            ->setConversationTitle("Conversation 1")
            ->setDeletedAt(new \DateTimeImmutable('2025-02-01'));

        $this->em->persist($deletion);
        $this->em->flush();

        // Exécution
        $messages = $this->repository->findConversationBetweenUsers($user1, $user2);

        $this->assertCount(2, $messages, 'Seul le message créé après la suppression doit apparaître');
        $this->assertEquals("Message récent", $messages[0]->getContent());
    }

    public function testMarkMessagesAsRead()
    {
        $user1 = (new User())->setPseudo('sender' . uniqid())->setEmail(uniqid('s') . '@test.com')->setPassword('pass');
        $user2 = (new User())->setPseudo('receiver' . uniqid())->setEmail(uniqid('r') . '@test.com')->setPassword('pass');
        $this->em->persist($user1);
        $this->em->persist($user2);

        $message1 = (new Message())
            ->setContent('Unread')
            ->setTitle('Test')
            ->setSender($user1)
            ->setReceiver($user2)
            ->setIsRead(false);
        $message2 = (new Message())
            ->setContent('Read')
            ->setTitle('Test')
            ->setSender($user1)
            ->setReceiver($user2)
            ->setIsRead(true);
        $this->em->persist($message1);
        $this->em->persist($message2);
        $this->em->flush();

        // Marquer comme lus
        $this->repository->markMessagesAsRead($user2, $user1);

        // Rafraîchir les entités
        $this->em->clear();
        $updatedMessages = $this->repository->findBy(['receiver' => $user2]);

        // Vérification
        foreach ($updatedMessages as $msg) {
            $this->assertTrue($msg->isRead());
        }
    }
}
