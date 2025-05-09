<?php

namespace App\Tests\Entity;

use App\Entity\{ForumResponse, User, Forum, Rating};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

class ForumResponseTest extends TestCase
{
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
        $this->assertFalse($user->getForumResponses()->contains($response));
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
        $this->assertFalse($forum->getResponses()->contains($response));
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
}
