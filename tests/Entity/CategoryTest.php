<?php

namespace App\Tests\Entity;

use App\Entity\{Category, Skill, Forum};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CategoryTest extends KernelTestCase

{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get('validator');
    }

    public function testGettersAndSetters(): void
    {
        $category = new Category();
        $category->setName('Développement Web');

        $this->assertEquals('Développement Web', $category->getName());
    }

    public function testInitialization(): void
    {
        $category = new Category();

        $this->assertInstanceOf(Collection::class, $category->getSkills());
        $this->assertInstanceOf(Collection::class, $category->getForums());
        $this->assertCount(0, $category->getSkills());
        $this->assertCount(0, $category->getForums());
    }

    public function testAddAndRemoveSkill(): void
    {
        $category = new Category();
        $skill = new Skill();

        // Test ajout
        $category->addSkill($skill);
        $this->assertCount(1, $category->getSkills());
        $this->assertSame($category, $skill->getCategory());

        // Test suppression
        $category->removeSkill($skill);
        $this->assertCount(0, $category->getSkills());
        $this->assertNull($skill->getCategory());
    }

    public function testAddAndRemoveForum(): void
    {
        $category = new Category();
        $forum = new Forum();

        // Test ajout
        $category->addForum($forum);
        $this->assertCount(1, $category->getForums());
        $this->assertSame($category, $forum->getCategory());

        // Test suppression
        $category->removeForum($forum);
        $this->assertCount(0, $category->getForums());
        $this->assertNull($forum->getCategory());
    }

    public function testToString(): void
    {
        $category = new Category();
        $category->setName('Design');

        $this->assertEquals('Design', (string)$category);
    }

    public function testSkillRelationshipConsistency(): void
    {
        $category1 = new Category();
        $category2 = new Category();
        $skill = new Skill();

        // Ajout à la première catégorie
        $category1->addSkill($skill);
        $this->assertSame($category1, $skill->getCategory());

        // Changement vers une deuxième catégorie
        $category2->addSkill($skill);
        $this->assertSame($category2, $skill->getCategory());
        $this->assertCount(0, $category1->getSkills()); // L'ancienne catégorie ne le contient plus
    }

    public function testForumRelationshipConsistency(): void
    {
        $category = new Category();
        $forum = new Forum();

        $category->addForum($forum);
        $this->assertSame($category, $forum->getCategory());

        $forum->setCategory(null);
        $this->assertCount(0, $category->getForums());
    }

    public function testDuplicateSkillAddition(): void
    {
        $category = new Category();
        $skill = new Skill();

        $category->addSkill($skill);
        $category->addSkill($skill); // Ajout doublon

        $this->assertCount(1, $category->getSkills());
    }

    public function testRemoveNonexistentSkill(): void
    {
        $category = new Category();
        $skill = new Skill();

        $category->removeSkill($skill); // Suppression non existante
        $this->assertCount(0, $category->getSkills());
    }

    public function testDuplicateForumAddition(): void
    {
        $category = new Category();
        $forum = new Forum();

        $category->addForum($forum);
        $category->addForum($forum); // Ajout doublon

        $this->assertCount(1, $category->getForums());
    }

    public function testRemoveNonexistentForum(): void
    {
        $category = new Category();
        $forum = new Forum();

        $category->removeForum($forum); // Suppression non existante
        $this->assertCount(0, $category->getForums());
    }

    public function testForumRelationshipBetweenCategories(): void
    {
        $category1 = new Category();
        $category2 = new Category();
        $forum = new Forum();

        $category1->addForum($forum);
        $this->assertSame($category1, $forum->getCategory());

        $category2->addForum($forum);
        $this->assertSame($category2, $forum->getCategory());
        $this->assertCount(0, $category1->getForums());
    }

    public function testNameValidationConstraints()
    {
        $category = new Category();
        $category->setName('A'); // Trop court

        $violations = $this->validator->validate($category);
        $messages = array_map(fn($v) => $v->getMessage(), iterator_to_array($violations));

        $this->assertCount(1, $violations);
        $this->assertContains('Le nom doit contenir au moins 2 caractères', $messages);
    }

    public function testNameLengthBoundaries()
    {
        $category = new Category();

        // Longueur minimale (2)
        $category->setName('Ab');
        $this->assertCount(0, $this->validator->validate($category));

        // Longueur maximale (50)
        $category->setName(str_repeat('a', 50));
        $this->assertCount(0, $this->validator->validate($category));
    }

    public function testNameNotBlank()
    {
        $category = new Category();
        $category->setName('');

        $violations = $this->validator->validate($category);
        $messages = array_map(fn($v) => $v->getMessage(), iterator_to_array($violations));

        // On vérifie maintenant 2 violations
        $this->assertCount(2, $violations);

        // On vérifie la présence des deux messages
        $this->assertContains('Le nom de la catégorie est obligatoire', $messages);
        $this->assertContains('Le nom doit contenir au moins 2 caractères', $messages);
    }
}
