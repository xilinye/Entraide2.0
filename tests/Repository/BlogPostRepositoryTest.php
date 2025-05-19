<?php

namespace App\Tests\Repository;

use App\Entity\{BlogPost, User};
use App\Repository\BlogPostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\ORM\Tools\SchemaTool;

class BlogPostRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private BlogPostRepository $repository;
    private User $user;
    private static bool $databaseInitialized = false;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::$kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        // Create schema only once per test run
        if (!self::$databaseInitialized) {
            $this->initializeDatabase();
            self::$databaseInitialized = true;
        }

        // Start transaction for each test
        $this->entityManager->beginTransaction();

        // Create test user with unique pseudo
        $this->user = new User();
        $this->user->setEmail('test@example.com');
        $this->user->setPassword('password');
        $this->user->setPseudo('test_user_' . uniqid());

        $this->entityManager->persist($this->user);
        $this->entityManager->flush();

        $this->repository = $this->entityManager->getRepository(BlogPost::class);
    }

    private function initializeDatabase(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        // Drop and recreate all tables
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testFindAllPublishedOrdersByCreatedAtDesc(): void
    {
        $post1 = $this->createBlogPost('Post 1', 'Content 1', 'post-1', new \DateTimeImmutable('2023-01-01'));
        $post2 = $this->createBlogPost('Post 2', 'Content 2', 'post-2', new \DateTimeImmutable('2023-01-02'));

        $this->entityManager->flush();

        $results = $this->repository->findAllPublished();

        $this->assertCount(2, $results);
        $this->assertSame('Post 2', $results[0]->getTitle());
        $this->assertSame('Post 1', $results[1]->getTitle());
    }

    public function testFindOneBySlug(): void
    {
        $post = $this->createBlogPost('Test Post', 'Test Content', 'test-slug');
        $this->entityManager->flush();

        $foundPost = $this->repository->findOneBySlug('test-slug');

        $this->assertNotNull($foundPost);
        $this->assertSame('Test Post', $foundPost->getTitle());
    }

    public function testSearchWithQuery(): void
    {
        $this->createBlogPost('PHP Tutorial', 'Learn PHP', 'php-tutorial');
        $this->createBlogPost('Symfony Tips', 'Symfony is great', 'symfony-tips');
        $this->entityManager->flush();

        // Test with lowercase query
        $results = $this->repository->search('php');
        $this->assertCount(1, $results);
        $this->assertSame('PHP Tutorial', $results[0]->getTitle());

        // Test with uppercase query
        $results = $this->repository->search('PHP');
        $this->assertCount(1, $results);
    }

    public function testSearchWithoutQuery(): void
    {
        $this->createBlogPost('Post 1', 'Content 1', 'post-1', new \DateTimeImmutable('2023-01-01'));
        $this->createBlogPost('Post 2', 'Content 2', 'post-2', new \DateTimeImmutable('2023-01-02'));
        $this->entityManager->flush();

        $results = $this->repository->search(null);

        $this->assertCount(2, $results);
        $this->assertSame('Post 2', $results[0]->getTitle());
        $this->assertSame('Post 1', $results[1]->getTitle());
    }

    private function createBlogPost(
        string $title,
        string $content,
        string $slug,
        \DateTimeImmutable $createdAt = null
    ): BlogPost {
        $post = new BlogPost();
        $post->setTitle($title);
        $post->setContent($content);
        $post->setSlug($slug);
        $post->setAuthor($this->user);
        $post->setCreatedAt($createdAt ?? new \DateTimeImmutable());

        $this->entityManager->persist($post);

        return $post;
    }

    protected function tearDown(): void
    {
        if ($this->entityManager && $this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
        parent::tearDown();
    }
}
