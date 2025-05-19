<?php

namespace App\Tests\Repository;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CategoryRepositoryTest extends KernelTestCase
{
    private EntityManager $entityManager;
    private CategoryRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        $this->repository = $this->entityManager->getRepository(Category::class);

        // Begin a transaction to isolate each test
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback the transaction to undo changes
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        $this->entityManager->close();
        unset($this->entityManager, $this->repository);
        parent::tearDown();
    }

    public function testFindAllOrdered(): void
    {
        // Create test categories in non-alphabetical order
        $category1 = new Category();
        $category1->setName('Beta');
        $this->entityManager->persist($category1);

        $category2 = new Category();
        $category2->setName('Alpha');
        $this->entityManager->persist($category2);

        $this->entityManager->flush();

        // Retrieve categories using the repository method
        $categories = $this->repository->findAllOrdered();

        // Assert the order is correct
        $this->assertCount(2, $categories);
        $this->assertEquals('Alpha', $categories[0]->getName());
        $this->assertEquals('Beta', $categories[1]->getName());
    }

    public function testExistsByName(): void
    {
        $existingName = 'TestCategory';
        $nonExistingName = 'NonExistent';

        // Create a category with the existing name
        $category = new Category();
        $category->setName($existingName);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        // Test existing name
        $this->assertTrue($this->repository->existsByName($existingName));
        // Test non-existing name
        $this->assertFalse($this->repository->existsByName($nonExistingName));
    }

    public function testRemoveCategory(): void
    {
        // Create a new category
        $category = new Category();
        $category->setName('TempCategory');
        $this->entityManager->persist($category);
        $this->entityManager->flush();
        $categoryId = $category->getId();

        // Remove the category and flush
        $this->repository->remove($category, true);

        // Ensure the category is removed
        $removedCategory = $this->repository->find($categoryId);
        $this->assertNull($removedCategory);
    }
}
