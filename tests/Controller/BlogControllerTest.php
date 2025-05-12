<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\{BlogPost, User, Rating};
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BlogControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $user;
    private $blogPost;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();

        // Générer un identifiant unique pour chaque test
        $uniqueId = uniqid();

        // Créer un utilisateur avec des données uniques
        $this->user = (new User())
            ->setPseudo('testuser_' . $uniqueId)
            ->setEmail('test_' . $uniqueId . '@example.com')
            ->setPassword('password')
            ->setIsVerified(true);
        $this->entityManager->persist($this->user);

        // Créer un blog post avec slug unique
        $this->blogPost = (new BlogPost())
            ->setTitle('Test Post ' . $uniqueId)
            ->setContent('Test Content')
            ->setAuthor($this->user)
            ->setSlug('test-post-' . $uniqueId);
        $this->entityManager->persist($this->blogPost);

        $this->entityManager->flush();
    }

    public function testIndex(): void
    {
        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/blog/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="search_blog"]');
    }

    public function testNewPostWithValidData(): void
    {
        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/blog/nouveau');

        // Vérifier que le formulaire existe
        $this->assertSelectorExists('form[name="blog_post_form"]');

        $form = $crawler->filter('form[name="blog_post_form"]')->form();

        // Générer des données test
        $unique = uniqid();
        $title = 'New Post ' . $unique;
        $content = 'Content ' . $unique;

        // Remplir le formulaire
        $form['blog_post_form[title]'] = $title;
        $form['blog_post_form[content]'] = $content;

        // Upload d'image valide
        $imagePath = __DIR__ . '/../fixtures/real-image.jpg'; // Utiliser une vraie image
        $image = new UploadedFile($imagePath, 'test.jpg', 'image/jpeg', null);
        $form['blog_post_form[imageFile]']->upload($image);

        // Soumettre le formulaire
        $this->client->submit($form);

        // Vérifier la redirection
        $this->assertResponseRedirects();
        $crawler = $this->client->followRedirect();

        // Vérifier l'affichage du nouvel article
        $this->assertSelectorTextContains('h1', $title);
        $this->assertSelectorTextContains('.content', $content);
    }

    public function testShowPostWithRating(): void
    {
        // Créer un utilisateur différent pour le test
        $rater = (new User())
            ->setPseudo('rater_' . uniqid())
            ->setEmail('rater_' . uniqid() . '@example.com')
            ->setPassword('password')
            ->setIsVerified(true);
        $this->entityManager->persist($rater);
        $this->entityManager->flush();

        $this->client->loginUser($rater);
        $crawler = $this->client->request('GET', '/blog/' . $this->blogPost->getSlug());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="rating"]');

        $form = $crawler->filter('form[name="rating"]')->form();
        $form['rating[score]'] = 5;
        $this->client->submit($form);

        $this->assertResponseRedirects();
    }

    public function testEditPost(): void
    {
        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/blog/' . $this->blogPost->getSlug() . '/edit');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="blog_post_form"]');

        $form = $crawler->filter('form[name="blog_post_form"]')->form();
        $newTitle = 'Updated Title ' . uniqid();
        $form['blog_post_form[title]'] = $newTitle;
        $this->client->submit($form);

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertSelectorTextContains('h1', $newTitle);
    }

    public function testDeletePost(): void
    {
        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/blog/' . $this->blogPost->getSlug());

        $this->assertSelectorExists('form[name="delete_form"]');
        $form = $crawler->filter('form[name="delete_form"]')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/blog/');
    }
}
