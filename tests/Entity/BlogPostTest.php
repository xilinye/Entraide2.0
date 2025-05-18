<?php

namespace App\Tests\Entity;

use App\Entity\BlogPost;
use App\Entity\Rating;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Validation;

class BlogPostTest extends TestCase
{
    private function createValidBlogPost(): BlogPost
    {
        $user = new User();
        $user->setEmail(uniqid('valid') . '@example.com');
        $user->setPassword('ValidPassword123!');
        $user->setPseudo(uniqid('valid_'));

        $blogPost = (new BlogPost())
            ->setTitle('Valid Title')
            ->setContent('This is a valid content with more than 10 characters.')
            ->setAuthor($user);

        $blogPost->computeSlug(new AsciiSlugger());
        return $blogPost;
    }

    public function testValidationConstraints()
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $slugger = new AsciiSlugger();

        // Test valid entity
        $blogPost = $this->createValidBlogPost();
        $blogPost->computeSlug($slugger);
        $violations = $validator->validate($blogPost);
        $this->assertCount(0, $violations);

        // Test title empty (NotBlank + Length)
        $emptyTitlePost = $this->createValidBlogPost()->setTitle('');
        $emptyTitlePost->computeSlug($slugger);
        $violations = $validator->validate($emptyTitlePost);
        $messages = array_map(fn($v) => $v->getMessage(), iterator_to_array($violations));
        $this->assertCount(2, $violations);
        $this->assertContains('Le titre ne peut pas être vide.', $messages);
        $this->assertContains('Le titre doit contenir au moins 5 caractères', $messages);

        // Test title too short (Length only)
        $shortTitlePost = $this->createValidBlogPost()->setTitle('abcd');
        $shortTitlePost->computeSlug($slugger);
        $violations = $validator->validate($shortTitlePost);
        $this->assertCount(1, $violations);
        $this->assertEquals('Le titre doit contenir au moins 5 caractères', $violations[0]->getMessage());

        // Test content empty
        $emptyContentPost = $this->createValidBlogPost()->setContent('');
        $violations = $validator->validate($emptyContentPost);
        $contentMessages = array_map(fn($v) => $v->getMessage(), iterator_to_array($violations));
        $this->assertCount(2, $violations);
        $this->assertContains('Le contenu ne peut pas être vide.', $contentMessages);
        $this->assertContains('Le contenu doit contenir au moins 10 caractères', $contentMessages);

        // Test author null
        $noAuthorPost = $this->createValidBlogPost()->setAuthor(null);
        $violations = $validator->validate($noAuthorPost);
        $this->assertCount(1, $violations);
        $this->assertEquals("L'auteur est obligatoire", $violations[0]->getMessage());
    }

    public function testTimestamps()
    {
        $blogPost = new BlogPost();

        // Check timestamps are equal upon creation
        $this->assertEquals($blogPost->getCreatedAt(), $blogPost->getUpdatedAt());

        // Force update and check updatedAt is later
        $originalUpdatedAt = $blogPost->getUpdatedAt();
        $blogPost->setTitle('Updated Title')->updateTimestamps();
        $this->assertGreaterThan($originalUpdatedAt, $blogPost->getUpdatedAt());
    }

    public function testSlugGeneration()
    {
        $slugger = new AsciiSlugger();
        $blogPost = $this->createValidBlogPost()->setTitle('Test Title');
        $blogPost->computeSlug($slugger);

        $slug = $blogPost->getSlug();
        $this->assertStringStartsWith('test-title-', $slug);
        $this->assertEquals(19, strlen($slug));
    }

    public function testRatingRelationships()
    {
        $blogPost = $this->createValidBlogPost();
        $rating = new Rating();

        $this->assertCount(0, $blogPost->getRatings());

        $blogPost->addRating($rating);
        $this->assertCount(1, $blogPost->getRatings());
        $this->assertSame($blogPost, $rating->getBlogPost());

        $blogPost->removeRating($rating);
        $this->assertCount(0, $blogPost->getRatings());
        $this->assertNull($rating->getBlogPost());
    }

    public function testImageHandling()
    {
        $blogPost = $this->createValidBlogPost();

        $this->assertNull($blogPost->getImageName());

        $blogPost->setImageName('image.jpg');
        $this->assertEquals('image.jpg', $blogPost->getImageName());

        $blogPost->setImageFile('dummy_file');
        $this->assertEquals('dummy_file', $blogPost->getImageFile());
    }

    public function testToString()
    {
        $blogPost = new BlogPost();
        $this->assertEquals('New Blog Post', (string)$blogPost);

        $blogPost->setTitle('My Post');
        $this->assertEquals('My Post', (string)$blogPost);
    }
}
