<?php

namespace App\Tests\Form;

use App\Entity\{BlogPost, User};
use App\Form\BlogPostFormType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\ORM\EntityManagerInterface;

class BlogPostFormTypeTest extends KernelTestCase
{
    private FormInterface $form;
    private User $testUser;

    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->beginTransaction();

        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'test@example.com']);

        if (!$user) {
            $user = new User();
            $user->setPseudo('testuser');
            $user->setEmail('test@example.com');
            $user->setPassword('password');
            $user->setRoles(['ROLE_USER']);
            $entityManager->persist($user);
            $entityManager->flush();
        }

        $this->testUser = $user;

        $blogPost = (new BlogPost())
            ->setAuthor($this->testUser)
            ->setTitle('Default Title')
            ->setContent('Default Content');

        $formFactory = self::getContainer()->get('form.factory');
        $this->form = $formFactory->create(
            BlogPostFormType::class,
            $blogPost,
            ['csrf_protection' => false]
        );
    }
    protected function tearDown(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->rollback();
        parent::tearDown();
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
            '5 M',
            implode(', ', $errorMessages),
            'Actual errors: ' . implode(', ', $errorMessages)
        );
    }

    public function testDataClassOptionSetCorrectly(): void
    {
        $formConfig = $this->form->getConfig();
        $this->assertEquals(BlogPost::class, $formConfig->getOption('data_class'));
    }

    public function testTitleNotBlank(): void
    {
        $this->form->submit([
            'title' => '', // Vide
            'content' => 'Contenu valide',
        ]);

        $this->assertFalse($this->form->isValid());
        // Correction du message attendu :
        $this->assertStringContainsString('Cette valeur ne doit pas être vide.', $this->form->get('title')->getErrors()[0]->getMessage());
    }

    public function testImageFileIsOptional(): void
    {
        $this->form->submit([
            'title' => 'Test Title', // Augmenté à 11 caractères
            'content' => 'Contenu valide suffisamment long',
            // Pas de imageFile
        ]);

        $this->assertTrue(
            $this->form->isValid(),
            "Erreurs de validation : " . (string) $this->form->getErrors(true)
        );
    }

    public function testContentNotBlank(): void
    {
        $this->form->submit([
            'title' => 'Titre valide',
            'content' => '', // Vide
        ]);

        $this->assertFalse($this->form->isValid());
        $this->assertStringContainsString('Cette valeur ne doit pas être vide.', $this->form->get('content')->getErrors()[0]->getMessage());
    }

    public function testImageFileValidationValidPngFile(): void
    {
        $imageFile = new UploadedFile(
            __DIR__ . '/../fixtures/valid_image.png',
            'valid_image.png',
            'image/png',
            null,
            true
        );

        $this->form->submit([
            'title' => 'Test Title',
            'content' => 'Test Content',
            'imageFile' => $imageFile
        ]);

        $this->assertTrue($this->form->isValid());
    }

    public function testFormInitialData(): void
    {
        $blogPost = new BlogPost();
        $blogPost->setTitle('Titre initial');
        $blogPost->setContent('Contenu initial');

        $form = self::getContainer()->get('form.factory')->create(BlogPostFormType::class, $blogPost);

        $this->assertEquals('Titre initial', $form->get('title')->getData());
        $this->assertEquals('Contenu initial', $form->get('content')->getData());
    }
}
