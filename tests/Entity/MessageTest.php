<?php

namespace App\Tests\Entity;

use App\Entity\Message;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validation;

class MessageTest extends TestCase
{
    private $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping(true)
            ->getValidator();
    }

    public function testInitialState()
    {
        $message = new Message();

        $this->assertFalse($message->isRead());
        $this->assertInstanceOf(\DateTimeImmutable::class, $message->getCreatedAt());
        $this->assertFalse($message->isRead());
        $this->assertSame(false, $message->isRead());
    }

    public function testGettersAndSetters()
    {
        $message = new Message();

        // Test content
        $content = 'Test content';
        $message->setContent($content);
        $this->assertEquals($content, $message->getContent());

        // Test sender
        $sender = new User();
        $message->setSender($sender);
        $this->assertSame($sender, $message->getSender());

        // Test receiver
        $receiver = new User();
        $message->setReceiver($receiver);
        $this->assertSame($receiver, $message->getReceiver());

        // Test createdAt
        $createdAt = new \DateTimeImmutable('2023-01-01');
        $message->setCreatedAt($createdAt);
        $this->assertEquals($createdAt, $message->getCreatedAt());

        // Test isRead
        $message->setIsRead(true);
        $this->assertTrue($message->isRead());

        // Test title
        $title = 'Test Title';
        $message->setTitle($title);
        $this->assertEquals($title, $message->getTitle());

        // Test imageName
        $imageName = 'image.jpg';
        $message->setImageName($imageName);
        $this->assertEquals($imageName, $message->getImageName());

        $message->setImageName(null);
        $this->assertNull($message->getImageName());

        // Test imageFile
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'dummy content');
        $imageFile = new UploadedFile(
            $tempFile,
            'file.jpg',
            'image/jpeg',
            null,
            true
        );
        $message->setImageFile($imageFile);
        $this->assertSame($imageFile, $message->getImageFile());
        unlink($tempFile);
    }

    public function testContentValidation()
    {
        $message = $this->createValidMessage();

        // Test NotBlank constraint
        $message->setContent('');
        $violations = $this->validator->validate($message);
        $this->assertCount(1, $violations);
        $this->assertEquals('Le contenu ne peut pas être vide.', $violations[0]->getMessage());

        // Test max length constraint
        $message->setContent(str_repeat('a', 2001));
        $violations = $this->validator->validate($message);
        $this->assertCount(1, $violations);
        $this->assertEquals('This value is too long. It should have 2000 characters or less.', $violations[0]->getMessage());

        // Test valid length
        $message->setContent(str_repeat('a', 2000));
        $violations = $this->validator->validate($message);
        $this->assertCount(0, $violations);
    }

    public function testTitleValidation()
    {
        $message = $this->createValidMessage();

        // Test NotBlank constraint
        $message->setTitle('');
        $violations = $this->validator->validate($message);
        $this->assertCount(1, $violations);
        $this->assertEquals('Le titre ne peut pas être vide.', $violations[0]->getMessage());

        // Test max length constraint
        $message->setTitle(str_repeat('a', 256));
        $violations = $this->validator->validate($message);
        $this->assertCount(1, $violations);
        $this->assertEquals('This value is too long. It should have 255 characters or less.', $violations[0]->getMessage());

        // Test valid length
        $message->setTitle(str_repeat('a', 255));
        $violations = $this->validator->validate($message);
        $this->assertCount(0, $violations);
    }

    public function testSenderAndReceiverNotNull()
    {
        $message = $this->createValidMessage();

        // Valide spécifiquement avec le groupe 'persist'
        $message->setSender(null);
        $violations = $this->validator->validate($message, null, ['persist']);
        $this->assertCount(1, $violations);
        $this->assertEquals('L\'expéditeur est requis.', $violations[0]->getMessage());

        $message = $this->createValidMessage();
        $message->setReceiver(null);
        $violations = $this->validator->validate($message, null, ['persist']);
        $this->assertCount(1, $violations);
        $this->assertEquals('Le destinataire est requis.', $violations[0]->getMessage());
    }

    public function testImageFileValidation()
    {
        $message = $this->createValidMessage();

        // Test max size constraint (6MB)
        $filePath6MB = tempnam(sys_get_temp_dir(), 'test_6mb');
        $handle = fopen($filePath6MB, 'r+');
        ftruncate($handle, 6 * 1024 * 1024); // 6MB (6,291,456 bytes)
        fclose($handle);

        $file6MB = new UploadedFile(
            $filePath6MB,
            'test_6mb.jpg',
            'image/jpeg',
            null,
            true
        );
        $message->setImageFile($file6MB);
        $violations = $this->validator->validate($message);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('The file is too large', $violations[0]->getMessage());
        unlink($filePath6MB);

        // Test valid size (5MB)
        $filePath5MB = tempnam(sys_get_temp_dir(), 'test_5mb');
        $handle = fopen($filePath5MB, 'r+');
        ftruncate($handle, 5 * 1000 * 1000);
        fclose($handle);

        $file5MB = new UploadedFile(
            $filePath5MB,
            'test_5mb.jpg',
            'image/jpeg',
            null,
            true
        );
        $message->setImageFile($file5MB);
        $violations = $this->validator->validate($message);
        $this->assertCount(0, $violations);
        unlink($filePath5MB);
    }

    public function testToString()
    {
        $message = new Message();

        // Contenu long (55 caractères)
        $longContent = str_repeat('a', 55);
        $message->setContent($longContent);
        $this->assertEquals(substr($longContent, 0, 50) . '...', (string)$message);

        // Contenu court (13 caractères)
        $shortContent = 'Short message';
        $message->setContent($shortContent);
        $this->assertEquals($shortContent, (string)$message); // Plus de '...'
    }

    public function testToStringEdgeCases()
    {
        $message = new Message();

        // Exactement 50 caractères
        $content50 = str_repeat('a', 50);
        $message->setContent($content50);
        $this->assertEquals($content50, (string)$message);

        // 49 caractères
        $content49 = str_repeat('a', 49);
        $message->setContent($content49);
        $this->assertEquals($content49, (string)$message); // Plus de '...'

        // Chaîne vide
        $message->setContent('');
        $this->assertEquals('', (string)$message);
    }

    public function testPrePersistSetsCreatedAt()
    {
        $message = new Message();
        $initialCreatedAt = $message->getCreatedAt();

        // Simulate modifying createdAt
        $newDate = new \DateTimeImmutable('2023-01-01');
        $message->setCreatedAt($newDate);
        $this->assertEquals($newDate, $message->getCreatedAt());

        // Trigger PrePersist lifecycle callback
        $message->setCreatedAtValue();

        // Verify that createdAt is updated to current time
        $this->assertNotEquals($newDate, $message->getCreatedAt());
        $this->assertTrue($message->getCreatedAt() > new \DateTimeImmutable('-5 seconds'));
    }

    private function createValidMessage(): Message
    {
        $message = new Message();
        $message->setContent('Valid content');
        $message->setTitle('Valid Title');
        $message->setSender(new User());
        $message->setReceiver(new User());

        return $message;
    }
}
