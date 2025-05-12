<?php

namespace App\Tests\Form;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BlogPostFormTypeTest extends WebTestCase
{
    public function testInvalidImageSubmission(): void
    {
        $client = static::createClient();

        // Créer et persister un utilisateur
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $user = (new User())
            ->setPseudo('testuser')
            ->setEmail('test@example.com')
            ->setPassword('password')
            ->setIsVerified(true);

        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);

        // Créer un fichier texte
        $txtPath = sys_get_temp_dir() . '/test-file.txt';
        file_put_contents($txtPath, 'contenu test');

        // Accéder à la page de création
        $crawler = $client->request('GET', '/blog/nouveau');

        // Sélectionner le bon bouton
        $form = $crawler->selectButton('Publier l\'article')->form();

        // Remplir le formulaire
        $form['blog_post_form[title]'] = 'Titre valide';
        $form['blog_post_form[content]'] = str_repeat('a', 15); // Contenu valide
        $form['blog_post_form[imageFile]'] = new UploadedFile(
            $txtPath,
            'test.txt',
            'text/plain',
            null,
            true
        );

        // Soumettre
        $client->submit($form);

        // Vérifier l'erreur
        $this->assertStringContainsString(
            'Format d\'image invalide',
            $client->getResponse()->getContent()
        );

        unlink($txtPath);
    }
}
