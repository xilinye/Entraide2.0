<?php

namespace App\Tests\Entity;

use App\Entity\Message;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MessageTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get('validator');
    }

    public function testValidMessage(): void
    {
        $message = $this->createValidMessage();
        $violations = $this->validator->validate($message);
        $this->assertCount(0, $violations);
    }

    public function testInitialState(): void
    {
        $message = new Message();
        $this->assertFalse($message->isRead());
        $this->assertInstanceOf(\DateTimeImmutable::class, $message->getCreatedAt());
    }

    public function testContentNotBlank(): void
    {
        $message = $this->createValidMessage()->setContent('');
        $this->assertValidationErrorCount($message, 1, 'Le contenu ne peut pas être vide.');
    }

    public function testContentMaxLength(): void
    {
        $content = str_repeat('a', 2001);
        $message = $this->createValidMessage()->setContent($content);
        $this->assertValidationErrorContains($message, '2000');
    }

    public function testTitleNotBlank(): void
    {
        $message = $this->createValidMessage()->setTitle('');
        $this->assertValidationErrorCount($message, 1, 'Le titre ne peut pas être vide.');
    }

    public function testTitleMaxLength(): void
    {
        $title = str_repeat('a', 256);
        $message = $this->createValidMessage()->setTitle($title);
        $this->assertValidationErrorContains($message, '255');
    }

    public function testImageFileMaxSize(): void
    {
        $file = $this->createTestFile(5 * 1024 * 1024 + 1);
        $message = $this->createValidMessage()->setImageFile($file);
        $this->assertValidationErrorContains($message, '5 MB');
        unlink($file->getPathname());
    }

    public function testSenderAndReceiver(): void
    {
        $sender = new User();
        $receiver = new User();
        $message = (new Message())
            ->setSender($sender)
            ->setReceiver($receiver);

        $this->assertSame($sender, $message->getSender());
        $this->assertSame($receiver, $message->getReceiver());
    }

    public function testToString(): void
    {
        $content = 'This is a message that exceeds fifty characters for testing truncation.';
        $message = (new Message())->setContent($content);
        $this->assertEquals(substr($content, 0, 50) . '...', (string)$message);
    }

    public function testImageHandling(): void
    {
        $message = new Message();
        $message->setImageName('image.jpg');
        $this->assertEquals('image.jpg', $message->getImageName());

        $file = $this->createTestFile(100);
        $message->setImageFile($file);
        $this->assertSame($file, $message->getImageFile());
        unlink($file->getPathname());
    }

    private function createValidMessage(): Message
    {
        return (new Message())
            ->setContent('Valid content')
            ->setTitle('Valid title')
            ->setSender(new User())
            ->setReceiver(new User());
    }

    private function createTestFile(int $size): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($path, str_repeat('a', $size));
        return new UploadedFile($path, 'testfile.jpg', 'image/jpeg', null, true);
    }

    private function assertValidationErrorCount(Message $message, int $expectedCount, string $messageText = null): void
    {
        $violations = $this->validator->validate($message);
        $this->assertCount($expectedCount, $violations);
        if ($messageText) {
            $this->assertEquals($messageText, $violations[0]->getMessage());
        }
    }

    private function assertValidationErrorContains(Message $message, string $expectedString): void
    {
        $violations = $this->validator->validate($message);
        $this->assertStringContainsString($expectedString, $violations[0]->getMessage());
    }
}
