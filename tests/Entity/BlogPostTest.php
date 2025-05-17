<?php

namespace App\Tests\Entity;

use App\Entity\{BlogPost, Rating, User};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class BlogPostTest extends KernelTestCase
{
    private ValidatorInterface $validator;
    private User $author;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
        $this->author = (new User())
            ->setEmail(uniqid('test') . '@example.com')
            ->setPseudo(uniqid('TestUser_'))
            ->setPassword('password')
            ->setRoles(['ROLE_USER']);
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
        $slugger = new AsciiSlugger();

        $blogPost = (new BlogPost())
            ->setTitle('Titre Valide')
            ->setContent('Contenu valide de plus de dix caractères')
            ->setAuthor($this->author);

        $blogPost->computeSlug($slugger);

        return $blogPost;
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
    public function testSlugUniqueness(): void
    {
        $slugger = new AsciiSlugger();
        $blogPost1 = $this->createValidBlogPost()->setTitle('Test');
        $blogPost1->computeSlug($slugger);

        $blogPost2 = $this->createValidBlogPost()->setTitle('Test');
        $blogPost2->computeSlug($slugger);

        $this->assertNotSame($blogPost1->getSlug(), $blogPost2->getSlug());
    }

    public function testRatingCascadeRemove(): void
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $slugger = new AsciiSlugger();

        // Créer et persister les utilisateurs
        $author = (new User())
            ->setEmail(uniqid('author') . '@example.com')
            ->setPseudo(uniqid('Author_'))
            ->setPassword('password');

        $rater = (new User())
            ->setEmail(uniqid('rater') . '@example.com')
            ->setPseudo(uniqid('Rater_'))
            ->setPassword('password');

        $entityManager->persist($author);
        $entityManager->persist($rater);
        $entityManager->flush();

        // Créer le BlogPost avec un Rating
        $blogPost = (new BlogPost())
            ->setTitle('Test Cascade Remove')
            ->setContent('Content')
            ->setAuthor($this->author);

        $blogPost->computeSlug($slugger);

        $rating = (new Rating())
            ->setScore(5)
            ->setRater($rater)
            ->setRatedUser($author);

        $blogPost->addRating($rating);

        // Persister et vérifier la cascade
        $entityManager->persist($blogPost);
        $entityManager->flush();

        // Vérifier que le Rating a un ID après persistance
        $this->assertNotNull($rating->getId(), 'Le Rating devrait avoir un ID après persistance');

        // Supprimer le BlogPost et vérifier la suppression en cascade
        $ratingId = $rating->getId();
        $entityManager->remove($blogPost);
        $entityManager->flush();

        // Rechercher le Rating par son ID
        $deletedRating = $entityManager->find(Rating::class, $ratingId);
        $this->assertNull($deletedRating, 'Le Rating devrait être supprimé en cascade');
    }

    public function testContentBoundary(): void
    {
        $blogPost = $this->createValidBlogPost()
            ->setContent(str_repeat('a', 9));

        $errors = $this->validator->validate($blogPost);
        $this->assertValidationErrorCount($errors, 1);

        $blogPost->setContent(str_repeat('a', 10));
        $errors = $this->validator->validate($blogPost);
        $this->assertValidationErrorCount($errors, 0);
    }
    public function testSlugUpdateOnTitleChange(): void
    {
        $slugger = new AsciiSlugger();
        $blogPost = $this->createValidBlogPost();
        $blogPost->computeSlug($slugger);
        $originalSlug = $blogPost->getSlug();

        $blogPost->setTitle('Nouveau Titre Modifié');
        $blogPost->computeSlug($slugger);

        $this->assertNotSame($originalSlug, $blogPost->getSlug());
        $this->assertStringContainsString('nouveau-titre-modifie', $blogPost->getSlug());
    }
    public function testImageNameNullable(): void
    {
        $blogPost = $this->createValidBlogPost()
            ->setImageName(null);

        $errors = $this->validator->validate($blogPost);
        $this->assertCount(0, $errors, 'imageName devrait être nullable');
    }
    public function testSlugUniquenessInDatabase(): void
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $slug = 'slug-unique-' . uniqid();

        // Premier blogPost avec slug unique
        $blogPost1 = $this->createValidBlogPost()
            ->setSlug($slug);

        // Deuxième blogPost avec même slug
        $blogPost2 = $this->createValidBlogPost()
            ->setSlug($slug)
            ->setTitle('Another Title');

        $entityManager->persist($blogPost1);
        $entityManager->flush();

        $this->expectException(UniqueConstraintViolationException::class);

        $entityManager->persist($blogPost2);
        $entityManager->flush();
    }
    public function testCreatedAtImmutable(): void
    {
        $blogPost = $this->createValidBlogPost();
        $originalCreatedAt = $blogPost->getCreatedAt();

        // Modification et sauvegarde
        $blogPost->setTitle('Titre Mis à Jour');
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $entityManager->persist($blogPost);
        $entityManager->flush();

        $this->assertSame(
            $originalCreatedAt->getTimestamp(),
            $blogPost->getCreatedAt()->getTimestamp(),
            'createdAt ne devrait pas changer après mise à jour'
        );
    }
}
