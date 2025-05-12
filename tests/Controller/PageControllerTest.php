<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

class PageControllerTest extends WebTestCase
{
    use MailerAssertionsTrait;

    // Test static pages return 200 and correct template
    public function testStaticPages(): void
    {
        $client = static::createClient();
        $pages = [
            '/' => 'Bienvenue sur Entr\'Aide 2.0',
            '/a-propos' => 'Notre Mission',
            '/conditions-utilisation' => 'Conditions Générales d\'Utilisation',
            '/confidentialite' => 'Politique de Confidentialité'
        ];

        foreach ($pages as $url => $expectedText) {
            $client->request('GET', $url);
            $this->assertSelectorTextContains('h1', $expectedText);
        }
    }
    // Test contact page displays form
    public function testContactPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="contact[name]"]');
        $this->assertSelectorExists('input[name="contact[email]"]');
        $this->assertSelectorExists('input[name="contact[subject]"]');
        $this->assertSelectorExists('textarea[name="contact[message]"]');
    }

    // Test invalid form submission
    public function testInvalidFormSubmission(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact');

        // Utiliser soit le texte du bouton soit son name HTML
        $client->submitForm('Envoyer le message', [
            'contact[name]' => '',
            'contact[email]' => 'invalid-email',
            'contact[subject]' => '',
            'contact[message]' => '',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.invalid-feedback'); // Vérifie les messages d'erreur
    }

    public function testValidFormSubmissionAndEmail(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact');

        $client->submitForm('Envoyer le message', [
            'contact[name]' => 'John Doe',
            'contact[email]' => 'john@example.com',
            'contact[subject]' => 'Test Subject',
            'contact[message]' => 'Hello! This is a test message.',
        ]);

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'Subject', 'Test Subject');
    }
}
