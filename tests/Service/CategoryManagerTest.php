<?php

namespace App\Tests\Service;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\CategoryManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CategoryManagerTest extends TestCase
{
    private const EXISTING_NAME = 'Existant';
    private const NEW_NAME = 'Nouveau';

    public function testCreateCategoryThrowsWhenNameExists(): void
    {
        // Arrange
        $category = new Category();
        $category->setName(self::EXISTING_NAME);

        $repositoryMock = $this->createRepositoryMock(true);
        $entityManagerMock = $this->createEntityManagerMock(false);

        // Act & Assert
        $manager = new CategoryManager($entityManagerMock, $repositoryMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Le nom de catégorie existe déjà');

        $manager->createCategory($category);
    }

    public function testCreateCategoryPersistsWhenNameIsUnique(): void
    {
        // Arrange
        $category = new Category();
        $category->setName(self::NEW_NAME);

        $repositoryMock = $this->createRepositoryMock(false);
        $entityManagerMock = $this->createEntityManagerMock(true);

        // Act
        $manager = new CategoryManager($entityManagerMock, $repositoryMock);
        $manager->createCategory($category);

        // Assert - No exception means success
    }

    private function createRepositoryMock(bool $nameExists): CategoryRepository
    {
        /** @var CategoryRepository&\PHPUnit\Framework\MockObject\MockObject $mock */
        $mock = $this->createMock(CategoryRepository::class);
        $mock->method('existsByName')
            ->willReturn($nameExists);

        return $mock;
    }

    private function createEntityManagerMock(bool $shouldPersist): EntityManagerInterface
    {
        /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $mock */
        $mock = $this->createMock(EntityManagerInterface::class);

        if ($shouldPersist) {
            $mock->expects($this->once())
                ->method('persist')
                ->with($this->isInstanceOf(Category::class));

            $mock->expects($this->once())
                ->method('flush');
        } else {
            $mock->expects($this->never())
                ->method('persist');

            $mock->expects($this->never())
                ->method('flush');
        }

        return $mock;
    }
}
