<?php

namespace App\Tests\Form;

use App\Entity\{Category, Forum, User};
use App\Form\ForumType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ForumTypeTest extends KernelTestCase
{
    private FormInterface $form;
    private Category $category;
    private User $author;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $this->createSchema($entityManager);

        // Créer un User valide
        $this->author = (new User())
            ->setPseudo('testuser')
            ->setEmail('test@example.com')
            ->setPassword('password');
        $entityManager->persist($this->author);

        $this->category = (new Category())->setName('Test Category');
        $entityManager->persist($this->category);
        $entityManager->flush();

        // Créer un Forum avec l'author
        $forum = (new Forum())->setAuthor($this->author); // Modification ici

        $formFactory = self::getContainer()->get('form.factory');
        $this->form = $formFactory->create(ForumType::class, $forum, [
            'csrf_protection' => false,
        ]);
    }

    public function testAuthorIsSet(): void
    {
        $formData = [
            'title' => 'Valid Title',
            'content' => 'Valid content for the forum post.',
            'category' => $this->category->getId(),
        ];

        $this->form->submit($formData);

        $this->assertTrue($this->form->isSubmitted());
        $this->assertTrue($this->form->isValid());

        /** @var Forum $forum */
        $forum = $this->form->getData();
        $this->assertSame($this->author->getId(), $forum->getAuthor()->getId());
    }

    public function testMultipleInvalidFields(): void
    {
        $this->form->submit([
            'title' => '', // Invalid
            'content' => '', // Invalid
            'category' => null, // Valid (optionnel)
        ]);

        $this->assertTrue($this->form->isSubmitted());
        $this->assertFalse($this->form->isValid());

        $titleErrors = $this->form->get('title')->getErrors();
        $contentErrors = $this->form->get('content')->getErrors();

        $this->assertCount(1, $titleErrors);
        $this->assertCount(1, $contentErrors);
    }

    public function testImageFileTooLarge(): void
    {
        $largeFilePath = tempnam(sys_get_temp_dir(), 'large');
        file_put_contents($largeFilePath, str_repeat('a', 6 * 1024 * 1024)); // 6 Mo
        $largeFile = new UploadedFile(
            $largeFilePath,
            'large_image.png',
            'image/png',
            null,
            true
        );

        $this->form->submit([
            'title' => 'Test Title',
            'content' => 'Test Content',
            'imageFile' => $largeFile,
        ]);

        unlink($largeFilePath); // Clean up temporary file

        $this->assertTrue($this->form->isSubmitted());
        $this->assertFalse($this->form->isValid());

        $imageFileErrors = $this->form->get('imageFile')->getErrors();
        $this->assertCount(1, $imageFileErrors);
        $this->assertStringContainsString('Sa taille ne doit pas dépasser 5 MB.', $imageFileErrors[0]->getMessage());
    }

    public function testCategoryAssociation(): void
    {
        $formData = [
            'title' => 'Valid Title',
            'content' => 'Valid content for the forum post.',
            'category' => $this->category->getId(),
        ];

        $this->form->submit($formData);

        $this->assertTrue($this->form->isSubmitted());
        $this->assertTrue($this->form->isValid());

        /** @var Forum $forum */
        $forum = $this->form->getData();
        $this->assertSame($this->category->getId(), $forum->getCategory()->getId());
    }
    private function createSchema($entityManager): void
    {
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }


    public function testSubmitValidData(): void
    {
        $formData = [
            'title' => 'Valid Title',
            'content' => 'Valid content for the forum post.',
            'category' => $this->category->getId()
        ];

        $this->form->submit($formData);

        $this->assertTrue($this->form->isSubmitted());
        $this->assertTrue($this->form->isValid());

        /** @var Forum $forum */
        $forum = $this->form->getData();
        $this->assertSame('Valid Title', $forum->getTitle());
        $this->assertSame('Valid content for the forum post.', $forum->getContent());
        $this->assertEquals($this->category->getId(), $forum->getCategory()->getId());
    }

    public function testRequiredFields(): void
    {
        $this->form->submit([
            'category' => $this->category->getId(),
        ]);

        $this->assertTrue($this->form->isSubmitted());
        $this->assertFalse($this->form->isValid());

        $this->assertTrue($this->form->get('title')->getErrors()->count() > 0);
        $this->assertTrue($this->form->get('content')->getErrors()->count() > 0);
    }

    public function testInvalidImageFile(): void
    {
        $invalidFilePath = tempnam(sys_get_temp_dir(), 'invalid');
        file_put_contents($invalidFilePath, 'dummy content');
        $invalidFile = new UploadedFile(
            $invalidFilePath,
            'invalid_file.txt',
            'text/plain',
            null,
            true
        );

        $this->form->submit([
            'title' => 'Test Title',
            'content' => 'Test Content',
            'imageFile' => $invalidFile,
        ]);

        unlink($invalidFilePath); // Clean up temporary file

        $this->assertTrue($this->form->isSubmitted());
        $this->assertFalse($this->form->isValid());

        $imageFileErrors = $this->form->get('imageFile')->getErrors();
        $this->assertCount(1, $imageFileErrors);
        $this->assertStringContainsString('Format d\'image invalide', $imageFileErrors[0]->getMessage());
    }

    public function testOptionalCategory(): void
    {
        $this->form->submit([
            'title' => 'Test Title',
            'content' => 'Test Content',
        ]);

        $this->assertTrue($this->form->isSubmitted());
        $this->assertTrue($this->form->isValid());
    }
    protected function tearDown(): void
    {
        parent::tearDown();
        self::ensureKernelShutdown();
    }
}
