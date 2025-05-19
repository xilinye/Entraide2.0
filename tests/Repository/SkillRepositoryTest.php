<?php

namespace App\Tests\Repository;

use App\Entity\{Category, Skill};
use App\Repository\SkillRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SkillRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private SkillRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::$kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        $this->repository = $this->entityManager->getRepository(Skill::class);
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
        parent::tearDown();
    }

    public function testFindBySearchFiltersWithQuery(): void
    {
        $category = $this->createCategory('Test Category');
        $this->createSkill('PHP Skill', $category);
        $this->createSkill('JavaScript Skill', $category);
        $this->entityManager->flush();

        $result = $this->repository->findBySearchFilters('PHP');
        $this->assertCount(1, $result);
        $this->assertEquals('PHP Skill', $result[0]->getName());
    }

    public function testFindBySearchFiltersWithCategory(): void
    {
        $category1 = $this->createCategory('Category 1');
        $category2 = $this->createCategory('Category 2');
        $this->createSkill('Skill 1', $category1);
        $this->createSkill('Skill 2', $category2);
        $this->entityManager->flush();

        $result = $this->repository->findBySearchFilters(null, $category1);
        $this->assertCount(1, $result);
        $this->assertEquals('Skill 1', $result[0]->getName());
    }

    public function testFindBySearchFiltersWithQueryAndCategory(): void
    {
        $category1 = $this->createCategory('Cat1');
        $category2 = $this->createCategory('Cat2');
        $this->createSkill('PHP Skill', $category1);
        $this->createSkill('JavaScript Skill', $category2);
        $this->createSkill('PHP Framework', $category1);
        $this->entityManager->flush();

        $result = $this->repository->findBySearchFilters('PHP', $category1);
        $this->assertCount(2, $result);
        $this->assertEquals('PHP Framework', $result[0]->getName());
        $this->assertEquals('PHP Skill', $result[1]->getName());
    }

    public function testFindBySearchFiltersWithoutFilters(): void
    {
        $category = $this->createCategory('Category');
        $this->createSkill('Skill 1', $category);
        $this->createSkill('Skill 2', $category);
        $this->entityManager->flush();

        $result = $this->repository->findBySearchFilters();
        $this->assertCount(2, $result);
    }

    public function testFindAllWithCategories(): void
    {
        $categoryB = $this->createCategory('B Category');
        $categoryA = $this->createCategory('A Category');
        $this->createSkill('Z Skill', $categoryB);
        $this->createSkill('A Skill', $categoryA);
        $this->entityManager->flush();

        $result = $this->repository->findAllWithCategories();
        $this->assertCount(2, $result);
        $this->assertEquals('A Skill', $result[0]->getName());
        $this->assertEquals('Z Skill', $result[1]->getName());
        $this->assertEquals('A Category', $result[0]->getCategory()->getName());
        $this->assertEquals('B Category', $result[1]->getCategory()->getName());
    }

    public function testFindRecent(): void
    {
        $category = $this->createCategory('Category');
        $this->createSkill('Skill 1', $category, new \DateTimeImmutable('2023-01-01'));
        $this->createSkill('Skill 2', $category, new \DateTimeImmutable('2023-01-03'));
        $this->createSkill('Skill 3', $category, new \DateTimeImmutable('2023-01-02'));
        $this->entityManager->flush();

        $result = $this->repository->findRecent(2);
        $this->assertCount(2, $result);
        $this->assertEquals('Skill 2', $result[0]->getName());
        $this->assertEquals('Skill 3', $result[1]->getName());
    }

    public function testRemoveSkill(): void
    {
        $category = $this->createCategory('Category');
        $skill = $this->createSkill('Skill', $category);
        $this->entityManager->flush();

        $skillId = $skill->getId();
        $this->assertNotNull($skillId, 'Skill ID should not be null after flush');

        $this->repository->remove($skill, true);

        $this->entityManager->clear();

        $removedSkill = $this->repository->find($skillId);
        $this->assertNull($removedSkill, 'Skill should not exist after removal');
    }

    private function createCategory(string $name): Category
    {
        $category = new Category();
        $category->setName($name);
        $this->entityManager->persist($category);
        return $category;
    }

    private function createSkill(string $name, Category $category, \DateTimeImmutable $createdAt = null): Skill
    {
        $skill = new Skill();
        $skill->setName($name)
            ->setCategory($category);

        if ($createdAt) {
            $reflection = new \ReflectionClass($skill);
            $createdAtProperty = $reflection->getProperty('createdAt');
            $createdAtProperty->setAccessible(true);
            $createdAtProperty->setValue($skill, $createdAt);
        }

        $this->entityManager->persist($skill);
        return $skill;
    }
}
