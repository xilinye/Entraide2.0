<?php

namespace App\Tests\Entity;

use App\Entity\BlogPost;
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
        $blogPost = (new BlogPost())
            ->setTitle('Titre valide')
            ->setContent('Ce contenu est valide car il dépasse dix caractères.');

        $erreurs = $this->validator->validate($blogPost);
        $this->assertCount(0, $erreurs, 'Aucune erreur ne devrait survenir');
    }

    public function testTitreNonVide(): void
    {
        $blogPost = (new BlogPost())
            ->setTitle('')
            ->setContent(str_repeat('a', 10));

        $erreurs = $this->validator->validate($blogPost);
        $this->assertCount(2, $erreurs);
    }

    public function testLongueurTitre(): void
    {
        // Trop court (min 5)
        $blogPost = (new BlogPost())
            ->setTitle('Test')
            ->setContent('Contenu valide');

        $erreurs = $this->validator->validate($blogPost);
        $this->assertCount(1, $erreurs, 'Le titre doit avoir au moins 5 caractères');

        // Trop long (max 255)
        $blogPost->setTitle(str_repeat('a', 256));
        $erreurs = $this->validator->validate($blogPost);
        $this->assertCount(1, $erreurs, 'Le titre ne doit pas dépasser 255 caractères');
    }

    public function testContenuNonVide(): void
    {
        $blogPost = (new BlogPost())
            ->setTitle('Titre valide')
            ->setContent('');

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
}
