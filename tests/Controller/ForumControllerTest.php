<?php

namespace App\Tests\Controller;

use App\Entity\{Forum, ForumResponse, User, Category, Rating};
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ForumControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $user;
    private $admin;
    private $forum;
    private $response;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        // Create test users with hashed passwords
        $this->user = (new User())
            ->setEmail(uniqid('user') . '@example.com')
            ->setPseudo(uniqid('user'))
            ->setPassword($passwordHasher->hashPassword(new User(), 'password'))
            ->setRoles(['ROLE_USER']);

        $this->admin = (new User())
            ->setEmail(uniqid('admin') . '@example.com')
            ->setPseudo(uniqid('admin'))
            ->setPassword($passwordHasher->hashPassword(new User(), 'password'))
            ->setRoles(['ROLE_ADMIN']);

        $this->entityManager->persist($this->user);
        $this->entityManager->persist($this->admin);

        // Create test category
        $category = (new Category())->setName('Test Category');
        $this->entityManager->persist($category);

        // Create test forum
        $this->forum = (new Forum())
            ->setTitle('Test Forum')
            ->setContent('Test Content')
            ->setAuthor($this->user)
            ->setCategory($category);
        $this->entityManager->persist($this->forum);

        // Create test response
        $this->response = (new ForumResponse())
            ->setContent('Test Response')
            ->setAuthor($this->user)
            ->setForum($this->forum);
        $this->entityManager->persist($this->response);

        $this->entityManager->flush();
    }

    public function testIndex(): void
    {
        $this->client->loginUser($this->user);
        $this->client->request('GET', '/forum/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Forum d\'entraide');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
