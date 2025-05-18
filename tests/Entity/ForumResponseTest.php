<?php

namespace App\Tests\Entity;

use App\Entity\{ForumResponse, User, Forum, Rating};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\{NotBlank, NotNull};

class ForumResponseTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }
    public function testInitialization(): void
    {
        $response = new ForumResponse();
        $this->assertInstanceOf(\DateTimeImmutable::class, $response->getCreatedAt());
        $this->assertCount(0, $response->getRatings());
        $this->assertNull($response->getId());
        $this->assertNull($response->getContent());
        $this->assertNull($response->getAuthor());
        $this->assertNull($response->getForum());
        $this->assertNull($response->getImageName());
        $this->assertNull($response->getImageFile());
    }

    public function testSetAndGetContent(): void
    {
        $response = new ForumResponse();
        $content = 'Test content';
        $response->setContent($content);
        $this->assertEquals($content, $response->getContent());
    }

    public function testSetAndGetAuthor(): void
    {
        $user = new User();
        $response = new ForumResponse();
        $response->setAuthor($user);
        $this->assertSame($user, $response->getAuthor());
        $this->assertTrue($user->getForumResponses()->contains($response));
    }

    public function testAddForumResponseToUser(): void
    {
        $user = new User();
        $response = new ForumResponse();
        $user->addForumResponse($response);
        $this->assertTrue($user->getForumResponses()->contains($response));
        $this->assertSame($user, $response->getAuthor());
    }

    public function testSetAndGetForum(): void
    {
        $forum = new Forum();
        $response = new ForumResponse();
        $response->setForum($forum);
        $this->assertSame($forum, $response->getForum());
        $this->assertTrue($forum->getResponses()->contains($response));
    }

    public function testAddResponseToForum(): void
    {
        $forum = new Forum();
        $response = new ForumResponse();
        $forum->addResponse($response);
        $this->assertTrue($forum->getResponses()->contains($response));
        $this->assertSame($forum, $response->getForum());
    }

    public function testAddAndRemoveRating(): void
    {
        $response = new ForumResponse();
        $rating = new Rating();
        $rating->setForumResponse($response);

        $this->assertTrue($response->getRatings()->contains($rating));
        $this->assertSame($response, $rating->getForumResponse());

        $rating->setForumResponse(null);
        $this->assertFalse($response->getRatings()->contains($rating));
        $this->assertNull($rating->getForumResponse());
    }

    public function testImageName(): void
    {
        $response = new ForumResponse();
        $imageName = 'image.jpg';
        $response->setImageName($imageName);
        $this->assertEquals($imageName, $response->getImageName());
    }

    public function testImageFile(): void
    {
        $response = new ForumResponse();
        // Créer un fichier temporaire et son chemin
        $cheminFichierTemp = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($cheminFichierTemp, 'contenu test');
        $fichier = new File($cheminFichierTemp);

        $response->setImageFile($fichier);
        $this->assertSame($fichier, $response->getImageFile());

        // Nettoyer le fichier temporaire
        unlink($cheminFichierTemp);
    }

    public function testSetCreatedAt(): void
    {
        $response = new ForumResponse();
        $date = new \DateTimeImmutable('2023-01-01');
        $response->setCreatedAt($date);
        $this->assertSame($date, $response->getCreatedAt());
    }

    public function testEmptyContent(): void
    {
        $response = new ForumResponse();
        $response->setContent('');
        $this->assertEmpty($response->getContent());
    }

    // Test de suppression d'un Rating non associé
    public function testRemoveNonExistentRating(): void
    {
        $response = new ForumResponse();
        $rating = new Rating();
        $response->removeRating($rating); // Aucune erreur ne doit survenir
        $this->assertCount(0, $response->getRatings());
    }

    // Test de définition de imageName à null
    public function testSetImageNameToNull(): void
    {
        $response = new ForumResponse();
        $response->setImageName(null);
        $this->assertNull($response->getImageName());
    }

    // Test d'ajout du même Rating deux fois
    public function testAddSameRatingTwice(): void
    {
        $response = new ForumResponse();
        $rating = new Rating();
        $response->addRating($rating);
        $response->addRating($rating); // Ne doit pas ajouter de doublon
        $this->assertCount(1, $response->getRatings());
    }

    private function validate(ForumResponse $response): array
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return iterator_to_array($validator->validate($response));
    }

    public function testValidationConstraints(): void
    {
        $response = new ForumResponse();
        $errors = $this->validate($response);

        $this->assertCount(3, $errors);
        $this->assertInstanceOf(NotBlank::class, $errors[0]->getConstraint());
        $this->assertInstanceOf(NotNull::class, $errors[1]->getConstraint());
        $this->assertInstanceOf(NotNull::class, $errors[2]->getConstraint());
    }

    public function testFileSizeValidation(): void
    {
        // Créer un fichier de 6MB
        $file = $this->createTempFile(6 * 1024 * 1024);
        $response = (new ForumResponse())
            ->setContent('Valid content')
            ->setAuthor(new User())
            ->setForum(new Forum())
            ->setImageFile($file);

        $errors = $this->validate($response);
        $this->assertCount(1, $errors);
    }

    public function testBidirectionalAuthorRelationship(): void
    {
        $user = new User();
        $response = new ForumResponse();
        $user->addForumResponse($response);

        $this->assertSame($user, $response->getAuthor());
        $this->assertTrue($user->getForumResponses()->contains($response));
    }

    public function testBidirectionalForumRelationship(): void
    {
        $forum = new Forum();
        $response = new ForumResponse();
        $forum->addResponse($response);

        $this->assertSame($forum, $response->getForum());
        $this->assertTrue($forum->getResponses()->contains($response));
    }

    public function testCascadeRatingRemoval(): void
    {
        $response = new ForumResponse();
        $rating = new Rating();
        $response->addRating($rating);

        // Simuler la suppression
        $response->removeRating($rating);

        $this->assertCount(0, $response->getRatings());
        $this->assertNull($rating->getForumResponse());
    }

    public function testFrenchValidationMessages(): void
    {
        $response = new ForumResponse();
        $errors = $this->validate($response, 'fr');

        $this->assertStringContainsString('Le contenu ne peut pas être vide', $errors[0]->getMessage());
    }

    public function testNonNullableRelations(): void
    {
        $response = new ForumResponse();
        $response->setContent('Valid content');

        $errors = $this->validate($response);
        $this->assertCount(2, $errors);
    }

    private function createTempFile(int $size): File
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        $handle = fopen($tempFile, 'w');
        ftruncate($handle, $size);
        fclose($handle);

        return new File($tempFile);
    }

    public function testUpdateAuthorRelationship(): void
    {
        $oldAuthor = new User();
        $newAuthor = new User();
        $response = new ForumResponse();

        $oldAuthor->addForumResponse($response);
        $newAuthor->addForumResponse($response);

        $this->assertSame($newAuthor, $response->getAuthor());
        $this->assertFalse($oldAuthor->getForumResponses()->contains($response));
    }

    public function testSetSameAuthorTwice(): void
    {
        $user = new User();
        $response = new ForumResponse();

        $user->addForumResponse($response);
        $user->addForumResponse($response); // Ne doit pas créer de doublon

        $this->assertCount(1, $user->getForumResponses());
    }
}
