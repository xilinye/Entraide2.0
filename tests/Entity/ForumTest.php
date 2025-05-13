<?php

namespace App\Tests\Entity;

use App\Entity\{Forum, User, Category, ForumResponse};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class ForumTest extends TestCase
{
    private function createValidForum(): Forum
    {
        $user = new User();
        $category = new Category();

        return (new Forum())
            ->setTitle('Problème avec les migrations')
            ->setContent('Je n\'arrive pas à exécuter mes migrations')
            ->setAuthor($user)
            ->setCategory($category);
    }

    public function testGettersAndSetters(): void
    {
        $forum = $this->createValidForum();
        $date = new \DateTimeImmutable('2023-01-01');

        $forum->setCreatedAt($date);
        $forum->setIsOpen(false);
        $forum->setImageName('forum.jpg');

        $this->assertEquals('Problème avec les migrations', $forum->getTitle());
        $this->assertFalse($forum->isOpen());
        $this->assertEquals($date, $forum->getCreatedAt());
        $this->assertEquals('forum.jpg', $forum->getImageName());
    }

    public function testValidationConstraints(): void
    {
        $translator = new \Symfony\Component\Translation\Translator('fr');
        $translator->addLoader('yaml', new \Symfony\Component\Translation\Loader\YamlFileLoader());
        $translator->addResource('yaml', __DIR__ . '/../../translations/validators.fr.yaml', 'validators', 'fr');

        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setTranslator($translator)
            ->getValidator();

        // Test entité invalide
        $invalidForum = new Forum();
        $errors = $validator->validate($invalidForum);

        $this->assertCount(3, $errors);

        $messages = [
            'title' => 'Le titre ne peut pas être vide',
            'content' => 'Le contenu ne peut pas être vide',
            'author' => 'L\'auteur est obligatoire'
        ];

        foreach ($errors as $error) {
            $field = $error->getPropertyPath();
            $this->assertArrayHasKey($field, $messages);
            $this->assertStringContainsString($messages[$field], $error->getMessage());
        }
    }

    public function testCategoryRelationship(): void
    {
        $forum = $this->createValidForum();
        $category = new Category();
        $category->setName('Symfony');

        $forum->setCategory($category);

        $this->assertSame($category, $forum->getCategory());
        $this->assertTrue($category->getForums()->contains($forum));
    }

    public function testAuthorRelationship(): void
    {
        $user = new User();
        $forum = $this->createValidForum()->setAuthor($user);

        $this->assertSame($user, $forum->getAuthor());
        $this->assertTrue($user->getForums()->contains($forum));
    }

    public function testResponsesManagement(): void
    {
        $forum = $this->createValidForum();
        $response = new ForumResponse();

        $forum->addResponse($response);
        $this->assertCount(1, $forum->getResponses());
        $this->assertSame($forum, $response->getForum());

        $forum->removeResponse($response);
        $this->assertCount(0, $forum->getResponses());
        $this->assertNull($response->getForum());
    }

    public function testImageFileConstraints(): void
    {
        $forum = $this->createValidForum();

        // Créez un fichier temporaire
        $tempFile = tmpfile();
        fwrite($tempFile, 'test');
        rewind($tempFile);

        $forum->setImageFile($tempFile);
        $errors = $this->validate($forum);
        $this->assertCount(0, $errors);
    }

    public function testTitleLengthValidation(): void
    {
        $forum = $this->createValidForum()
            ->setTitle(str_repeat('a', 256));

        $errors = $this->validate($forum);
        $this->assertCount(1, $errors);
    }

    public function testToString(): void
    {
        $forum = $this->createValidForum();
        $this->assertStringContainsString('Problème avec les migrations', (string)$forum);
    }

    public function testDefaultValues(): void
    {
        $forum = new Forum();
        $this->assertTrue($forum->isOpen());
        $this->assertInstanceOf(\DateTimeImmutable::class, $forum->getCreatedAt());
    }

    private function validate(Forum $forum): \Symfony\Component\Validator\ConstraintViolationList
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return $validator->validate($forum);
    }

    public function testCategoryRemoval(): void
    {
        $category = new Category();
        $forum = $this->createValidForum()->setCategory($category);
        $originalCategory = $forum->getCategory();
        $forum->setCategory(null);

        $this->assertNull($forum->getCategory());
        $this->assertFalse($originalCategory->getForums()->contains($forum));
    }

    public function testAuthorChangeUpdatesRelations(): void
    {
        $oldAuthor = new User();
        $newAuthor = new User();
        $forum = $this->createValidForum()->setAuthor($oldAuthor);

        $forum->setAuthor($newAuthor);

        $this->assertFalse($oldAuthor->getForums()->contains($forum));
        $this->assertTrue($newAuthor->getForums()->contains($forum));
    }
}
