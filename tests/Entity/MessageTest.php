<?php

namespace App\Tests\Entity;

use App\Entity\{Message, User};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validation;

class MessageTest extends TestCase
{
    private Message $message;
    private User $sender;
    private User $receiver;

    protected function setUp(): void
    {
        $this->message = new Message();
        $this->sender = new User();
        $this->receiver = new User();
    }

    // Test des getters/setters de base
    public function testBasicGettersAndSetters(): void
    {
        $this->message->setContent('Test content');
        $this->assertSame('Test content', $this->message->getContent());

        $this->message->setTitle('Test title');
        $this->assertSame('Test title', $this->message->getTitle());

        $this->message->setImageName('image.jpg');
        $this->assertSame('image.jpg', $this->message->getImageName());

        $this->message->setIsRead(true);
        $this->assertTrue($this->message->isRead());
    }

    // Test des relations avec User
    public function testSenderAndReceiverRelations(): void
    {
        // Utilisation des méthodes addSentMessage/addReceivedMessage
        $this->sender->addSentMessage($this->message);
        $this->receiver->addReceivedMessage($this->message);

        $this->assertSame($this->sender, $this->message->getSender());
        $this->assertSame($this->receiver, $this->message->getReceiver());

        // Vérification des collections
        $this->assertTrue($this->sender->getSentMessages()->contains($this->message));
        $this->assertTrue($this->receiver->getReceivedMessages()->contains($this->message));
    }

    // Test des valeurs par défaut
    public function testDefaultValues(): void
    {
        $this->assertFalse($this->message->isRead());
        $this->assertNotNull($this->message->getCreatedAt());
    }

    // Test de validation des contraintes
    public function testValidationConstraints(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        // Test contenu vide
        $message = new Message();
        $errors = $validator->validate($message);
        $this->assertGreaterThan(0, count($errors));

        // Test contenu trop long
        $message->setContent(str_repeat('a', 2001));
        $message->setTitle('Test');
        $errors = $validator->validate($message);
        $this->assertGreaterThan(0, count($errors));

        // Test titre vide
        $message->setContent('Valid content');
        $message->setTitle('');
        $errors = $validator->validate($message);
        $this->assertGreaterThan(0, count($errors));
    }

    // Test de gestion des fichiers image
    public function testImageFileConstraints(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        // Remplir les champs obligatoires
        $this->message->setContent('Contenu valide');
        $this->message->setTitle('Titre valide');

        // Test fichier trop lourd
        $file = new UploadedFile(
            __DIR__ . '/test_files/big_image.jpg',
            'big_image.jpg',
            'image/jpeg',
            null,
            true
        );
        $this->message->setImageFile($file);
        $errors = $validator->validate($this->message);
        $this->assertGreaterThan(0, count($errors));

        // Test type MIME invalide
        $file = new UploadedFile(
            __DIR__ . '/test_files/document.pdf',
            'document.pdf',
            'application/pdf',
            null,
            true
        );
        $this->message->setImageFile($file);
        $errors = $validator->validate($this->message);
        $this->assertGreaterThan(0, count($errors));

        // Test fichier valide
        $file = new UploadedFile(
            __DIR__ . '/test_files/valid_image.jpg',
            'valid_image.jpg',
            'image/jpeg',
            null,
            true
        );
        $this->message->setImageFile($file);
        $errors = $validator->validate($this->message);
        $this->assertCount(0, $errors);
    }

    // Test du formatage de la chaîne
    public function testToString(): void
    {
        $content = 'Il s\'agit d\'un long contenu de message qui doit être tronqué';
        $this->message->setContent($content);

        $expected = substr($content, 0, 50) . '...';
        $this->assertSame($expected, (string)$this->message);
    }

    // Test du cycle de vie pour createdAt
    public function testPrePersist(): void
    {
        $message = new Message();
        $this->assertInstanceOf(\DateTimeImmutable::class, $message->getCreatedAt());
    }

    // Test des messages marqués comme lus
    public function testReadStatus(): void
    {
        $this->message->setIsRead(true);
        $this->assertTrue($this->message->isRead());
    }
}
