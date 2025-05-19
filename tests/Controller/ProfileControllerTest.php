<?php

namespace App\Tests\Controller;

use App\Entity\{Skill, User};
use App\Service\{SkillManager, UserManager};
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProfileControllerTest extends WebTestCase
{
    private $client;
    private $userManagerMock;
    private $skillManagerMock;
    private $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Create test user
        $this->user = (new User())
            ->setEmail(uniqid('user') . '@example.com')
            ->setPseudo(uniqid('testuser'))
            ->setPassword('password');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->persist($this->user);
        $entityManager->flush();

        // Mock services
        $this->userManagerMock = $this->createMock(UserManager::class);
        $this->skillManagerMock = $this->createMock(SkillManager::class);

        // Inject mocks
        self::getContainer()->set(UserManager::class, $this->userManagerMock);
        self::getContainer()->set(SkillManager::class, $this->skillManagerMock);

        // Authenticate properly with persisted user
        $this->client->loginUser($this->user);
        $this->client->disableReboot();
    }

    public function testIndex(): void
    {
        $this->client->request('GET', '/profile/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mon Profil');
    }

    public function testManageSkillsGet(): void
    {
        $this->client->request('GET', '/profile/competences');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="skill_selection"]');
    }

    protected function tearDown(): void
    {
        // Clean up database
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $user = $entityManager->merge($this->user);
        $entityManager->remove($this->user);
        $entityManager->flush();

        parent::tearDown();
        $this->client = null;
    }
}
