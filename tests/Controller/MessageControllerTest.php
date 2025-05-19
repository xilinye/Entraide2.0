<?php

namespace App\Tests\Controller;

use App\Entity\{User, Message, ConversationDeletion};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MessageControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();
        $this->connection = $this->entityManager->getConnection();
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }

    public function testIndex(): void
    {
        $user = $this->createUser('user1');
        $this->client->loginUser($user);

        $this->client->request('GET', '/messages/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Vos conversations');
    }

    public function testConversationView(): void
    {
        $user1 = $this->createUser('user1');
        $user2 = $this->createUser('user2');
        $this->createMessage($user1, $user2, 'Hello', 'Test Title');

        $this->client->loginUser($user1);
        $crawler = $this->client->request('GET', "/messages/conversation/{$user2->getId()}?title=Test+Title");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.message-content', 'Hello');
    }

    private function createUser(string $pseudo, array $roles = ['ROLE_USER']): User
    {
        $uniquePseudo = $pseudo . '_' . uniqid();
        $uniqueEmail = $uniquePseudo . '@example.com';

        $user = new User();
        $user->setPseudo($uniquePseudo)
            ->setEmail($uniqueEmail)
            ->setPassword('password')
            ->setRoles($roles);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $user;
    }

    private function createMessage(User $sender, User $receiver, string $content, string $title): Message
    {
        $message = new Message();
        $message->setSender($sender)
            ->setReceiver($receiver)
            ->setContent($content)
            ->setTitle($title);
        $this->entityManager->persist($message);
        $this->entityManager->flush();
        return $message;
    }

    private function assertFlashMessageContains(string $type, string $message): void
    {
        $this->assertStringContainsString($message, $this->client->getResponse()->getContent());
    }
}
