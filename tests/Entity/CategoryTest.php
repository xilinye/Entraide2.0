<?php

namespace App\Tests\Entity;

use App\Entity\{Category, Skill, Forum};
use PHPUnit\Framework\TestCase;
use Doctrine\Common\Collections\Collection;

class CategoryTest extends TestCase
{
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
}
