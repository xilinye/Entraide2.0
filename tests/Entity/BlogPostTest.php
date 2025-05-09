<?php

namespace App\Tests\Entity;

use App\Entity\{BlogPost, Rating, User};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BlogPostTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel([
            'environment' => 'test',
            'debug' => true
        ]);

        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    public function testBlogPostValide(): void
    {
        $author = new User();
        $blogPost = (new BlogPost())
            ->setTitle('Titre valide')
            ->setContent('Ce contenu est valide car il dépasse dix caractères.')
            ->setAuthor($author);

        $erreurs = $this->validator->validate($blogPost);
        $this->assertCount(0, $erreurs, 'Aucune erreur ne devrait survenir');
    }

    public function testTitreNonVide(): void
    {
        $author = new User();
        $blogPost = (new BlogPost())
            ->setTitle('')
            ->setContent(str_repeat('a', 10))
            ->setAuthor($author);

        $erreurs = $this->validator->validate($blogPost);
        $this->assertCount(2, $erreurs);
    }

    public function testLongueurTitre(): void
    {
        $author = new User();
        $blogPost = (new BlogPost())
            ->setTitle('Test')
            ->setContent('Contenu valide')
            ->setAuthor($author);

        $erreurs = $this->validator->validate($blogPost);
        $this->assertCount(1, $erreurs, 'Le titre doit avoir au moins 5 caractères');

        $blogPost->setTitle(str_repeat('a', 256));
        $erreurs = $this->validator->validate($blogPost);
        $this->assertCount(1, $erreurs, 'Le titre ne doit pas dépasser 255 caractères');
    }

    public function testContenuNonVide(): void
    {
        $author = new User();
        $blogPost = (new BlogPost())
            ->setTitle('Titre valide')
            ->setContent('')
            ->setAuthor($author);

        $erreurs = $this->validator->validate($blogPost);
        $this->assertCount(2, $erreurs);
    }

    public function testLongueurContenu(): void
    {
        $blogPost = (new BlogPost())
            ->setTitle('Titre valide')
            ->setContent('Court');

        $erreurs = $this->validator->validate($blogPost);
        $this->assertCount(1, $erreurs);
    }

    public function testSlugGeneration(): void
    {
        $slugger = new \Symfony\Component\String\Slugger\AsciiSlugger();
        $blogPost = new BlogPost();
        $blogPost->setTitle('Titre Valide');
        $blogPost->computeSlug($slugger);

        $this->assertMatchesRegularExpression('/^titre-valide-[a-f0-9]{8}$/', $blogPost->getSlug());
    }
    public function testAutoTimestamps(): void
    {
        $blogPost = new BlogPost();
        $this->assertInstanceOf(\DateTimeImmutable::class, $blogPost->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $blogPost->getUpdatedAt());
    }

    public function testUpdateTimestampOnChange(): void
    {
        $blogPost = new BlogPost();
        $initialUpdate = $blogPost->getUpdatedAt();

        $blogPost->setTitle('Nouveau titre');
        $blogPost->updateTimestamps();

        $this->assertGreaterThan($initialUpdate, $blogPost->getUpdatedAt());
    }
    public function testAuthorAssociation(): void
    {
        $user = new User();
        $blogPost = new BlogPost();
        $user->addBlogPost($blogPost);

        $this->assertSame($user, $blogPost->getAuthor());
        $this->assertContains($blogPost, $user->getBlogPosts());
    }

    public function testRatingsCollection(): void
    {
        $rating = new Rating();
        $blogPost = new BlogPost();
        $blogPost->getRatings()->add($rating);
        $rating->setBlogPost($blogPost);

        $this->assertCount(1, $blogPost->getRatings());
        $this->assertSame($blogPost, $rating->getBlogPost());
    }
    public function testImageProperties(): void
    {
        $blogPost = new BlogPost();
        $blogPost->setImageName('image.jpg');
        $blogPost->setImageFile('dummy/file');

        $this->assertEquals('image.jpg', $blogPost->getImageName());
        $this->assertEquals('dummy/file', $blogPost->getImageFile());
    }
    public function testToString(): void
    {
        $blogPost = new BlogPost();
        $this->assertEquals('New Blog Post', (string)$blogPost);

        $blogPost->setTitle('Mon Article');
        $this->assertEquals('Mon Article', (string)$blogPost);
    }
    public function testConstructorInitialization(): void
    {
        $blogPost = new BlogPost();

        $this->assertEmpty($blogPost->getSlug());
        $this->assertNotNull($blogPost->getCreatedAt());
        $this->assertNotNull($blogPost->getUpdatedAt());
        $this->assertTrue($blogPost->getCreatedAt() <= $blogPost->getUpdatedAt());
    }
}
