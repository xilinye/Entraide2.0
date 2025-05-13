<?php

namespace App\Tests\Entity;

use App\Entity\{BlogPost, Rating, User};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BlogPostTest extends KernelTestCase
{
    private ValidatorInterface $validator;
    private User $author;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
        $this->author = (new User())->setEmail('test@example.com');
    }

    public function testValidBlogPost(): void
    {
        $blogPost = $this->createValidBlogPost();
        $this->assertValidationPasses($blogPost);
    }

    public function testTitleValidation(): void
    {
        $blogPost = $this->createValidBlogPost()
            ->setTitle('');

        $errors = $this->validator->validate($blogPost);
        $this->assertValidationErrorCount($errors, 2, 'Titre vide');

        $blogPost->setTitle(str_repeat('a', 256));
        $errors = $this->validator->validate($blogPost);
        $this->assertValidationErrorCount($errors, 1, 'Titre trop long');
    }

    public function testContentValidation(): void
    {
        $blogPost = $this->createValidBlogPost()
            ->setContent('');

        $errors = $this->validator->validate($blogPost);
        $this->assertValidationErrorCount($errors, 2, 'Contenu vide');

        $blogPost->setContent('Court');
        $errors = $this->validator->validate($blogPost);
        $this->assertValidationErrorCount($errors, 1, 'Contenu trop court');
    }

    public function testAuthorValidation(): void
    {
        $blogPost = $this->createValidBlogPost()
            ->setAuthor(null);

        $errors = $this->validator->validate($blogPost);
        $this->assertValidationErrorCount($errors, 1, 'Auteur manquant');
    }

    public function testSlugGeneration(): void
    {
        $slugger = new AsciiSlugger();
        $blogPost = $this->createValidBlogPost();
        $blogPost->computeSlug($slugger);

        $this->assertMatchesRegularExpression(
            '/^titre-valide-[a-f0-9]{8}$/',
            $blogPost->getSlug()
        );
    }

    public function testTimestampsLifecycle(): void
    {
        $blogPost = new BlogPost();
        $blogPost->setTitle('Test')
            ->setContent('Content')
            ->setAuthor($this->author);

        // Simule PrePersist
        $blogPost->updateTimestamps();
        $this->assertNotNull($blogPost->getCreatedAt());
        $this->assertNotNull($blogPost->getUpdatedAt());
        $this->assertEquals(
            $blogPost->getCreatedAt()->format('Y-m-d H:i:s'),
            $blogPost->getUpdatedAt()->format('Y-m-d H:i:s'),
            'createdAt et updatedAt doivent être égaux après la création'
        );

        // Simule PreUpdate avec délai
        $originalUpdatedAt = $blogPost->getUpdatedAt();
        sleep(1); // Garantit une différence de temps
        $blogPost->setTitle('Updated');
        $blogPost->updateTimestamps();

        $this->assertGreaterThan(
            $originalUpdatedAt,
            $blogPost->getUpdatedAt(),
            'updatedAt doit être mis à jour après une modification'
        );
    }

    public function testRatingManagement(): void
    {
        $blogPost = $this->createValidBlogPost();
        $rating = (new Rating())->setScore(5);

        $blogPost->addRating($rating);
        $this->assertCount(1, $blogPost->getRatings());
        $this->assertSame($blogPost, $rating->getBlogPost());

        $blogPost->removeRating($rating);
        $this->assertCount(0, $blogPost->getRatings());
        $this->assertNull($rating->getBlogPost());
    }

    public function testImageHandling(): void
    {
        $blogPost = $this->createValidBlogPost()
            ->setImageName('test.jpg')
            ->setImageFile('file.data');

        $this->assertEquals('test.jpg', $blogPost->getImageName());
        $this->assertEquals('file.data', $blogPost->getImageFile());
    }

    public function testToString(): void
    {
        $blogPost = new BlogPost();
        $this->assertEquals('New Blog Post', (string)$blogPost);

        $blogPost->setTitle('My Post');
        $this->assertEquals('My Post', (string)$blogPost);
    }

    private function createValidBlogPost(): BlogPost
    {
        return (new BlogPost())
            ->setTitle('Titre Valide')
            ->setContent('Contenu valide de plus de dix caractères')
            ->setAuthor($this->author);
    }

    private function assertValidationPasses(BlogPost $blogPost, string $message = ''): void
    {
        $errors = $this->validator->validate($blogPost);
        $this->assertCount(0, $errors, $message);
    }

    private function assertValidationErrorCount($errors, int $expected, string $message = ''): void
    {
        $this->assertCount(
            $expected,
            $errors,
            $message . "\n" . implode("\n", array_map(fn($e) => $e->getMessage(), iterator_to_array($errors)))
        );
    }
}
