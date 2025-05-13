<?php

namespace App\Tests\Form;

use App\Entity\{BlogPost, User};
use App\Form\BlogPostFormType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BlogPostFormTypeTest extends KernelTestCase
{
    private FormInterface $form;
    private User $testUser;

    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get('doctrine')->getManager();

        // Create or retrieve the test user
        $userRepository = $entityManager->getRepository(User::class);
        $this->testUser = $userRepository->findOneBy(['email' => 'test@example.com']);

        if (!$this->testUser) {
            $this->testUser = new User();
            $this->testUser->setEmail('test@example.com');
            $this->testUser->setPassword('password');
            // Set any additional required fields for your User entity
            $entityManager->persist($this->testUser);
            $entityManager->flush();
        }

        // Create a BlogPost with the test user as the author
        $blogPost = new BlogPost();
        $blogPost->setAuthor($this->testUser);

        // Initialize the form with the pre-authored BlogPost
        $formFactory = self::getContainer()->get('form.factory');
        $this->form = $formFactory->create(
            BlogPostFormType::class,
            $blogPost,
            ['csrf_protection' => false]
        );
    }

    public function testFormFields(): void
    {
        $formView = $this->form->createView();
        $children = $formView->children;

        $this->assertArrayHasKey('title', $children);
        $this->assertArrayHasKey('content', $children);
        $this->assertArrayHasKey('imageFile', $children);
    }

    public function testImageFileFieldNotMapped(): void
    {
        $imageFileField = $this->form->get('imageFile');
        $this->assertFalse($imageFileField->getConfig()->getOption('mapped'));
    }

    public function testSubmitValidData(): void
    {
        $formData = [
            'title' => 'Test Title',
            'content' => 'Test Content',
        ];
        $this->form->submit($formData);

        $this->assertTrue($this->form->isSynchronized());
        $this->assertEquals($formData['title'], $this->form->getData()->getTitle());
        $this->assertEquals($formData['content'], $this->form->getData()->getContent());
    }


    public function testImageFileValidationValidFile(): void
    {
        $imageFile = new UploadedFile(
            __DIR__ . '/../fixtures/valid_image.jpg',
            'valid_image.jpg',
            'image/jpeg',
            null,
            true
        );

        $this->form->submit([
            'title' => 'Test Title',
            'content' => 'Test Content',
            'imageFile' => $imageFile
        ]);

        $this->assertTrue($this->form->isValid(), (string) $this->form->getErrors(true));
    }

    public function testImageFileValidationInvalidMimeType(): void
    {
        $invalidFile = new UploadedFile(
            __DIR__ . '/../fixtures/invalid_file.pdf',
            'invalid_file.pdf',
            'application/pdf',
            null,
            true
        );

        $this->form->submit([
            'title' => 'Test Title',
            'content' => 'Test Content',
            'imageFile' => $invalidFile
        ]);

        $errors = $this->form->get('imageFile')->getErrors();
        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[] = $error->getMessage();
        }

        $this->assertContains('Format d\'image invalide', $errorMessages);
    }

    public function testImageFileValidationExceedsMaxSize(): void
    {
        $imageFile = new UploadedFile(
            __DIR__ . '/../fixtures/large_image.jpg',
            'large_image.jpg',
            'image/jpeg',
            null,
            true
        );

        $this->form->submit([
            'title' => 'Test Title',
            'content' => 'Test Content',
            'imageFile' => $imageFile
        ]);

        $errors = $this->form->get('imageFile')->getErrors();
        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[] = $error->getMessage();
        }

        $this->assertStringContainsString(
            '5 MB',
            implode(', ', $errorMessages),
            'Actual errors: ' . implode(', ', $errorMessages)
        );
    }

    public function testDataClassOptionSetCorrectly(): void
    {
        $formConfig = $this->form->getConfig();
        $this->assertEquals(BlogPost::class, $formConfig->getOption('data_class'));
    }
}
