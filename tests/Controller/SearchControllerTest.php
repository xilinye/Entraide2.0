<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\{User, Category, Skill};
use Doctrine\ORM\EntityManagerInterface;

class SearchControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $userRepository;
    private $categoryRepository;
    private $skillRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // Clear existing data
        $this->clearDatabase();

        $this->userRepository = $this->entityManager->getRepository(User::class);
        $this->categoryRepository = $this->entityManager->getRepository(Category::class);
        $this->skillRepository = $this->entityManager->getRepository(Skill::class);
    }

    private function clearDatabase(): void
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();
        foreach ($users as $user) {
            $this->entityManager->remove($user);
        }

        $skills = $this->entityManager->getRepository(Skill::class)->findAll();
        foreach ($skills as $skill) {
            $this->entityManager->remove($skill);
        }

        $categories = $this->entityManager->getRepository(Category::class)->findAll();
        foreach ($categories as $category) {
            $this->entityManager->remove($category);
        }

        $this->entityManager->flush();
    }

    private function createTestUser(string $email, array $skills = []): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPseudo(explode('@', $email)[0] . '_' . uniqid());
        $user->setPassword('password');
        $user->setIsVerified(true);

        foreach ($skills as $skill) {
            $user->addSkill($skill);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createTestCategory(string $name): Category
    {
        $category = new Category();
        $category->setName($name);
        $this->entityManager->persist($category);
        $this->entityManager->flush();
        return $category;
    }

    private function createTestSkill(string $name, Category $category): Skill
    {
        $skill = new Skill();
        $skill->setName($name . '_' . uniqid());
        $skill->setCategory($category);
        $this->entityManager->persist($skill);
        $this->entityManager->flush();
        return $skill;
    }

    public function testIndexRouteWithNoFilters(): void
    {
        $category = $this->createTestCategory('Programming');
        $skill = $this->createTestSkill('PHP', $category);
        $currentUser = $this->createTestUser('current@test.com', [$skill]);
        $otherUser = $this->createTestUser('other@test.com', [$skill]);

        $this->client->loginUser($currentUser);

        $this->client->request('GET', '/search/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Recherche');

        $users = $this->userRepository->findByFilters(null, null, $currentUser);
        $this->assertCount(1, $users);
        $this->assertSame($otherUser->getEmail(), $users[0]->getEmail());
    }

    public function testIndexRouteWithCategoryFilter(): void
    {
        $category1 = $this->createTestCategory('Programming');
        $category2 = $this->createTestCategory('Design');
        $skill1 = $this->createTestSkill('PHP', $category1);
        $skill2 = $this->createTestSkill('Photoshop', $category2);

        $currentUser = $this->createTestUser('current@test.com');
        $user1 = $this->createTestUser('user1@test.com', [$skill1]);
        $user2 = $this->createTestUser('user2@test.com', [$skill2]);

        $this->client->loginUser($currentUser);

        $this->client->request('GET', '/search/?category=' . $category1->getId());

        $this->assertResponseIsSuccessful();

        $users = $this->userRepository->findByFilters($category1, null, $currentUser);
        $this->assertCount(1, $users);
        $this->assertSame($user1->getEmail(), $users[0]->getEmail());
    }

    public function testIndexRouteWithSkillFilter(): void
    {
        $category = $this->createTestCategory('Programming');
        $skill1 = $this->createTestSkill('PHP', $category);
        $skill2 = $this->createTestSkill('JavaScript', $category);

        $currentUser = $this->createTestUser('current@test.com');
        $user1 = $this->createTestUser('user1@test.com', [$skill1]);
        $user2 = $this->createTestUser('user2@test.com', [$skill2]);

        $this->client->loginUser($currentUser);

        $this->client->request('GET', '/search/?skill=' . $skill1->getId());

        $this->assertResponseIsSuccessful();

        $users = $this->userRepository->findByFilters(null, $skill1, $currentUser);
        $this->assertCount(1, $users);
        $this->assertSame($user1->getEmail(), $users[0]->getEmail());
    }

    public function testSkillsByCategoryRouteWithCategory(): void
    {
        $user = $this->createTestUser('test@example.com');
        $this->client->loginUser($user);

        $category = $this->createTestCategory('Programming');
        $skill1 = $this->createTestSkill('PHP', $category);
        $skill2 = $this->createTestSkill('JavaScript', $category);

        $this->client->request('GET', '/search/skills?categoryId=' . $category->getId());

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $skillNames = array_column($response, 'name');
        $this->assertContains($skill1->getName(), $skillNames);
        $this->assertContains($skill2->getName(), $skillNames);
    }

    public function testSkillsByCategoryRouteWithoutCategory(): void
    {
        $user = $this->createTestUser('test@example.com');
        $this->client->loginUser($user);

        $category1 = $this->createTestCategory('Programming');
        $category2 = $this->createTestCategory('Design');
        $skill1 = $this->createTestSkill('PHP', $category1);
        $skill2 = $this->createTestSkill('Photoshop', $category2);

        $this->client->request('GET', '/search/skills');

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $skillNames = array_column($response, 'name');
        $this->assertCount(2, $response);
        $this->assertContains($skill1->getName(), $skillNames);
        $this->assertContains($skill2->getName(), $skillNames);
    }

    public function testUnauthorizedAccess(): void
    {
        $this->client->request('GET', '/search/');
        $this->assertResponseRedirects('/login');
    }
}
