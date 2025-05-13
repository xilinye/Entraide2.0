<?php

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\Skill;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SkillTest extends KernelTestCase
{
    private ValidatorInterface $validator;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel([
            'environment' => 'test',
            'debug' => true
        ]);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->beginTransaction();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    public function testValidSkill(): void
    {
        $category = new Category();
        $category->setName('Valid Category');
        $this->entityManager->persist($category);

        $skill = new Skill();
        $skill->setName('PHP')
            ->setDescription('Programming language')
            ->setCategory($category);

        $errors = $this->validator->validate($skill);
        $this->assertCount(0, $errors);
    }

    public function testNameNotBlank(): void
    {
        $category = new Category();
        $category->setName('Test Category');
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        $skill = new Skill();
        $skill->setName("")
            ->setCategory($category);

        $errors = $this->validator->validate($skill);
        $this->assertCount(2, $errors);
        $this->assertEquals('Le nom est obligatoire', $errors[0]->getMessage());
    }

    public function testNameLength(): void
    {
        $category = new Category();
        $category->setName('Test Category');
        $this->entityManager->persist($category);

        $skill = new Skill();
        $skill->setName('A')
            ->setCategory($category);

        $errors = $this->validator->validate($skill);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Le nom doit contenir au moins 2 caractères', $errors[0]->getMessage());

        $skill->setName(str_repeat('a', 256));
        $errors = $this->validator->validate($skill);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Le nom ne peut pas dépasser 255 caractères', $errors[0]->getMessage());
    }

    public function testDescriptionLength(): void
    {
        $category = new Category();
        $category->setName('Test Category');
        $this->entityManager->persist($category);

        $skill = new Skill();
        $skill->setName('PHP')
            ->setCategory($category)
            ->setDescription(str_repeat('a', 1001));

        $errors = $this->validator->validate($skill);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('La description ne peut pas dépasser 1000 caractères', $errors[0]->getMessage());
    }

    public function testCategoryNotNull(): void
    {
        $skill = new Skill();
        $skill->setName('PHP');

        $errors = $this->validator->validate($skill);
        $this->assertCount(1, $errors);
        $this->assertEquals('Veuillez choisir une catégorie.', $errors[0]->getMessage());
    }

    public function testSkillPersistence(): void
    {
        $name = 'PHP_' . uniqid();
        $category = new Category();
        $category->setName('Programming');
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        $skill = new Skill();
        $skill->setName($name)
            ->setCategory($category);

        $this->entityManager->persist($skill);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $retrievedSkill = $this->entityManager->getRepository(Skill::class)->find($skill->getId());
        $this->assertEquals($name, $retrievedSkill->getName());
        $this->assertEquals('Programming', $retrievedSkill->getCategory()->getName());
    }

    public function testUniqueNameAndCategoryConstraint(): void
    {
        $category = new Category();
        $category->setName('Programming');
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        $uniqueName = 'PHP_UniqueTest_' . uniqid();

        $skill1 = new Skill();
        $skill1->setName($uniqueName)
            ->setCategory($category);
        $this->entityManager->persist($skill1);
        $this->entityManager->flush();

        $skill2 = new Skill();
        $skill2->setName($uniqueName)
            ->setCategory($category);

        $errors = $this->validator->validate($skill2);
        $this->assertCount(1, $errors);
        $this->assertEquals('Cette compétence existe déjà pour cette catégorie', $errors[0]->getMessage());
    }

    public function testUserSkillBidirectionalRelationship(): void
    {
        $category = new Category();
        $category->setName('Test Category');
        $this->entityManager->persist($category);

        $user = new User();
        $user->setPseudo('john_doe_' . uniqid())
            ->setEmail('john_' . uniqid() . '@example.com')
            ->setPassword('password');


        $skillName = 'PHP_BidirectionalTest_' . uniqid();
        $skill = new Skill();
        $skill->setName($skillName)
            ->setCategory($category);

        $skill->addUser($user);
        $this->entityManager->persist($user);
        $this->entityManager->persist($skill);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $retrievedUser = $this->entityManager->getRepository(User::class)->find($user->getId());
        $retrievedSkill = $this->entityManager->getRepository(Skill::class)->find($skill->getId());

        $this->assertTrue($retrievedUser->getSkills()->contains($retrievedSkill));
        $this->assertTrue($retrievedSkill->getUsers()->contains($retrievedUser));
    }

    public function testSetCategoryUpdatesRelations(): void
    {
        $oldCategory = new Category();
        $oldCategory->setName('Old Category');
        $this->entityManager->persist($oldCategory);

        $newCategory = new Category();
        $newCategory->setName('New Category');
        $this->entityManager->persist($newCategory);

        $skill = new Skill();
        $skill->setName('Skill_' . uniqid())
            ->setCategory($oldCategory);
        $this->entityManager->persist($skill);
        $this->entityManager->flush();

        $this->assertContains($skill, $oldCategory->getSkills());

        $skill->setCategory($newCategory);
        $this->entityManager->flush();

        $this->entityManager->refresh($oldCategory);
        $this->entityManager->refresh($newCategory);

        $this->assertNotContains($skill, $oldCategory->getSkills());
        $this->assertContains($skill, $newCategory->getSkills());
    }

    public function testCreatedAtAutomaticallySet(): void
    {
        $category = new Category();
        $category->setName('Test Category');
        $this->entityManager->persist($category);

        $skill = new Skill();
        $skill->setName('Test_' . uniqid())
            ->setCategory($category);

        $this->entityManager->persist($skill);
        $this->entityManager->flush();

        $this->assertNotNull($skill->getCreatedAt());
    }

    public function testToStringReturnsName(): void
    {
        $skill = new Skill();
        $skill->setName('PHP');
        $this->assertEquals('PHP', (string) $skill);
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback(); // Annulation des changements
        }
        $this->entityManager->clear();
        parent::tearDown();
    }

    public function testRemoveUserUpdatesBidirectionalRelationship(): void
    {
        $category = new Category();
        $category->setName('Test Category');
        $this->entityManager->persist($category);

        $user = new User();
        $user->setPseudo('test_user')
            ->setEmail('test_' . uniqid() . '@example.com')
            ->setPassword('password');

        $skill = new Skill();
        $skill->setName('Skill')
            ->setCategory($category)
            ->addUser($user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($skill);
        $this->entityManager->flush();

        $skill->removeUser($user);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $retrievedUser = $this->entityManager->find(User::class, $user->getId());
        $this->assertFalse($retrievedUser->getSkills()->contains($skill));
    }

    public function testUniqueConstraintOnUpdate(): void
    {
        $category = new Category();
        $category->setName('Test Category');
        $this->entityManager->persist($category);

        $skill1 = new Skill();
        $skill1->setName('Skill')
            ->setCategory($category);
        $this->entityManager->persist($skill1);
        $this->entityManager->flush();

        $skill2 = new Skill();
        $skill2->setName('Another Skill')
            ->setCategory($category);
        $this->entityManager->persist($skill2);
        $this->entityManager->flush();

        // Tentative de modification pour violer l'unicité
        $skill2->setName('Skill');
        $errors = $this->validator->validate($skill2);
        $this->assertCount(1, $errors);
    }

    public function testValidNameBoundaries(): void
    {
        $category = new Category();
        $category->setName('Test Category');
        $this->entityManager->persist($category);

        // Test 2 caractères (min)
        $skill = new Skill();
        $skill->setName('ab')
            ->setCategory($category);
        $this->assertCount(0, $this->validator->validate($skill));

        // Test 255 caractères (max)
        $skill->setName(str_repeat('a', 255));
        $this->assertCount(0, $this->validator->validate($skill));
    }

    public function testNullDescriptionIsValid(): void
    {
        $category = new Category();
        $category->setName('Test Category');
        $this->entityManager->persist($category);

        $skill = new Skill();
        $skill->setName('Valid Skill')
            ->setCategory($category)
            ->setDescription(null);

        $errors = $this->validator->validate($skill);
        $this->assertCount(0, $errors);
    }

    public function testSameNameInDifferentCategories(): void
    {
        $category1 = new Category();
        $category1->setName('Category 1');
        $this->entityManager->persist($category1);

        $category2 = new Category();
        $category2->setName('Category 2');
        $this->entityManager->persist($category2);

        $skill1 = new Skill();
        $skill1->setName('Same Name')
            ->setCategory($category1);

        $skill2 = new Skill();
        $skill2->setName('Same Name')
            ->setCategory($category2);

        $this->assertCount(0, $this->validator->validate($skill1));
        $this->assertCount(0, $this->validator->validate($skill2));
    }

    public function testCreatedAtNotUpdatedOnModification(): void
    {
        $category = new Category();
        $category->setName('Test Category');
        $this->entityManager->persist($category);

        $skill = new Skill();
        $skill->setName('Test Skill')
            ->setCategory($category);
        $this->entityManager->persist($skill);
        $this->entityManager->flush();

        $originalDate = $skill->getCreatedAt();

        $skill->setDescription('Updated description');
        $this->entityManager->flush();

        $this->assertEquals($originalDate, $skill->getCreatedAt());
    }
}
