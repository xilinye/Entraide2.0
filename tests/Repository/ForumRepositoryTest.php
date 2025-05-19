<?php

namespace App\Tests\Repository;

use App\Entity\{Category, User, Forum};
use App\Repository\ForumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ForumRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ForumRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();

        $this->em->getConnection()->executeQuery('DELETE FROM forum');
        $this->em->getConnection()->executeQuery('DELETE FROM category');
        $this->em->getConnection()->executeQuery('DELETE FROM user');

        $this->repository = $this->em->getRepository(Forum::class);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail(uniqid('test') . '@example.com');
        $user->setPassword('password');
        $user->setPseudo('testuser' . uniqid());
        return $user;
    }

    public function testSearchWithoutFiltersReturnsAllForumsOrderedByNewest(): void
    {
        $user = $this->createUser();
        $this->em->persist($user);

        $category = new Category();
        $category->setName('Test Category');
        $this->em->persist($category);

        $forum1 = new Forum();
        $forum1->setTitle('Forum 1');
        $forum1->setContent('Content 1');
        $forum1->setAuthor($user);
        $forum1->setCreatedAt(new \DateTimeImmutable('2023-01-01'));
        $forum1->setCategory($category);
        $this->em->persist($forum1);

        $forum2 = new Forum();
        $forum2->setTitle('Forum 2');
        $forum2->setContent('Content 2');
        $forum2->setAuthor($user);
        $forum2->setCreatedAt(new \DateTimeImmutable('2023-01-03'));
        $forum2->setCategory($category);
        $this->em->persist($forum2);

        $forum3 = new Forum();
        $forum3->setTitle('Forum 3');
        $forum3->setContent('Content 3');
        $forum3->setAuthor($user);
        $forum3->setCreatedAt(new \DateTimeImmutable('2023-01-02'));
        $this->em->persist($forum3);

        $this->em->flush();

        $result = $this->repository->searchByQueryAndCategory();

        $this->assertCount(3, $result);
        $this->assertSame('Forum 2', $result[0]->getTitle());
        $this->assertSame('Forum 3', $result[1]->getTitle());
        $this->assertSame('Forum 1', $result[2]->getTitle());
    }

    public function testSearchWithQueryReturnsMatchingForums(): void
    {
        $user = $this->createUser();
        $this->em->persist($user);

        $forum1 = new Forum();
        $forum1->setTitle('PHP Tips');
        $forum1->setContent('Content without PHP');
        $forum1->setAuthor($user);
        $this->em->persist($forum1);

        $forum2 = new Forum();
        $forum2->setTitle('Symfony');
        $forum2->setContent('Learn PHP framework');
        $forum2->setAuthor($user);
        $this->em->persist($forum2);

        $forum3 = new Forum();
        $forum3->setTitle('Java');
        $forum3->setContent('Java content');
        $forum3->setAuthor($user);
        $this->em->persist($forum3);

        $this->em->flush();

        $result = $this->repository->searchByQueryAndCategory('PHP');

        $this->assertCount(2, $result);
        $titles = array_map(fn($forum) => $forum->getTitle(), $result);
        $this->assertContains('PHP Tips', $titles);
        $this->assertContains('Symfony', $titles);
    }

    public function testSearchWithCategoryReturnsForumsInCategory(): void
    {
        $user = $this->createUser();
        $this->em->persist($user);

        $category1 = new Category();
        $category1->setName('Cat1');
        $this->em->persist($category1);

        $category2 = new Category();
        $category2->setName('Cat2');
        $this->em->persist($category2);

        // Set content for all forums
        $forum1 = new Forum();
        $forum1->setTitle('Forum 1');
        $forum1->setContent('Content 1'); // Added
        $forum1->setAuthor($user);
        $forum1->setCategory($category1);
        $forum1->setCreatedAt(new \DateTimeImmutable('2023-01-01'));
        $this->em->persist($forum1);

        $forum2 = new Forum();
        $forum2->setTitle('Forum 2');
        $forum2->setContent('Content 2'); // Added
        $forum2->setAuthor($user);
        $forum2->setCategory($category2);
        $forum2->setCreatedAt(new \DateTimeImmutable('2023-01-02'));
        $this->em->persist($forum2);

        $forum3 = new Forum();
        $forum3->setTitle('Forum 3');
        $forum3->setContent('Content 3'); // Added
        $forum3->setAuthor($user);
        $forum3->setCategory($category1);
        $forum3->setCreatedAt(new \DateTimeImmutable('2023-01-03'));
        $this->em->persist($forum3);

        $this->em->flush();

        $result = $this->repository->searchByQueryAndCategory(null, $category1);

        $this->assertCount(2, $result);
        $this->assertSame('Forum 3', $result[0]->getTitle());
        $this->assertSame('Forum 1', $result[1]->getTitle());
    }

    public function testSearchWithQueryAndCategoryCombined(): void
    {
        $user = $this->createUser();
        $this->em->persist($user);

        $category1 = new Category();
        $category1->setName('Cat1');
        $this->em->persist($category1);

        $category2 = new Category();
        $category2->setName('Cat2');
        $this->em->persist($category2);

        $forum1 = new Forum();
        $forum1->setTitle('test forum');
        $forum1->setContent('Content');
        $forum1->setAuthor($user);
        $forum1->setCategory($category1);
        $this->em->persist($forum1);

        $forum2 = new Forum();
        $forum2->setTitle('Another forum');
        $forum2->setContent('No match');
        $forum2->setAuthor($user);
        $forum2->setCategory($category1);
        $this->em->persist($forum2);

        $forum3 = new Forum();
        $forum3->setTitle('test in category2');
        $forum3->setContent('Content');
        $forum3->setAuthor($user);
        $forum3->setCategory($category2);
        $this->em->persist($forum3);

        $this->em->flush();

        $result = $this->repository->searchByQueryAndCategory('test', $category1);

        $this->assertCount(1, $result);
        $this->assertSame('test forum', $result[0]->getTitle());
    }

    public function testSearchResultsAreOrderedByCreatedAtDesc(): void
    {
        $user = $this->createUser();
        $this->em->persist($user);

        $category = new Category();
        $category->setName('Test Category');
        $this->em->persist($category);

        $forum1 = new Forum();
        $forum1->setTitle('Forum 1');
        $forum1->setContent('Content 1');
        $forum1->setAuthor($user);
        $forum1->setCategory($category);
        $forum1->setCreatedAt(new \DateTimeImmutable('2023-01-01'));
        $this->em->persist($forum1);

        $forum2 = new Forum();
        $forum2->setTitle('Forum 2');
        $forum2->setContent('Content 2');
        $forum2->setAuthor($user);
        $forum1->setCategory($category);
        $forum2->setCreatedAt(new \DateTimeImmutable('2023-01-03'));
        $this->em->persist($forum2);

        $forum3 = new Forum();
        $forum3->setTitle('Forum 3');
        $forum3->setContent('Content 3');
        $forum3->setAuthor($user);
        $forum1->setCategory($category);
        $forum3->setCreatedAt(new \DateTimeImmutable('2023-01-02'));
        $this->em->persist($forum3);

        $this->em->flush();

        $result = $this->repository->searchByQueryAndCategory();

        $this->assertCount(3, $result);
        $this->assertSame('Forum 2', $result[0]->getTitle());
        $this->assertSame('Forum 3', $result[1]->getTitle());
        $this->assertSame('Forum 1', $result[2]->getTitle());
    }

    protected function tearDown(): void
    {
        // Rollback transaction to reset database state
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollback();
        }
        $this->em->close();
        unset($this->em, $this->repository);
        parent::tearDown();
    }
}
