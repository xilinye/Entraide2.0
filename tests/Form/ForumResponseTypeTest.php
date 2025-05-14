<?php

namespace App\Tests\Form;

use App\Entity\{ForumResponse, User, Forum};
use App\Form\ForumResponseType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ForumResponseTypeTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->formFactory = self::getContainer()->get('form.factory');
    }

    public function testFormFieldsAndConfiguration(): void
    {
        $form = $this->formFactory->create(ForumResponseType::class, null, [
            'csrf_protection' => false,
        ]);

        $this->assertTrue($form->has('content'));
        $this->assertTrue($form->has('imageFile'));

        $contentField = $form->get('content');
        $this->assertFalse($contentField->getConfig()->getOption('label'));
        $contentAttrs = $contentField->getConfig()->getOption('attr');
        $this->assertEquals(4, $contentAttrs['rows']);
        $this->assertEquals('Votre réponse...', $contentAttrs['placeholder']);

        $imageFileField = $form->get('imageFile');
        $this->assertEquals('Nouvelle image', $imageFileField->getConfig()->getOption('label'));
        $this->assertFalse($imageFileField->getConfig()->getOption('required'));
    }

    public function testSubmitValidData(): void
    {
        $forumResponse = new ForumResponse();
        $form = $this->formFactory->create(ForumResponseType::class, $forumResponse, [
            'csrf_protection' => false,
        ]);

        $tempFilePath = tempnam(sys_get_temp_dir(), 'test');
        // Create a valid 1x1 pixel JPEG image
        $image = imagecreatetruecolor(1, 1);
        imagejpeg($image, $tempFilePath);
        imagedestroy($image);

        $imageFile = new UploadedFile(
            $tempFilePath,
            'testfile.jpg',
            'image/jpeg',
            UPLOAD_ERR_OK,
            true
        );

        $form->submit([
            'content' => 'Valid content',
            'imageFile' => $imageFile,
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('Valid content', $forumResponse->getContent());
        $this->assertSame($imageFile, $forumResponse->getImageFile());

        unlink($tempFilePath);
    }

    public function testImageFileSizeConstraint(): void
    {
        $forumResponse = new ForumResponse();
        $form = $this->formFactory->create(ForumResponseType::class, $forumResponse, [
            'csrf_protection' => false,
        ]);

        $tempFilePath = tempnam(sys_get_temp_dir(), 'test');
        $tempFile = fopen($tempFilePath, 'w');
        ftruncate($tempFile, 5 * 1024 * 1024 + 1);
        fclose($tempFile);

        $imageFile = new UploadedFile(
            $tempFilePath,
            'largefile.jpg',
            'image/jpeg',
            UPLOAD_ERR_OK,
            true
        );

        $form->submit([
            'content' => 'Valid content',
            'imageFile' => $imageFile,
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());

        $errors = $form->getErrors(true);
        $this->assertStringContainsString('Sa taille ne doit pas dépasser 5 MB.', $errors->current()->getMessage());

        unlink($tempFilePath);
    }

    public function testImageFileIsOptional(): void
    {
        $forumResponse = new ForumResponse();
        $form = $this->formFactory->create(ForumResponseType::class, $forumResponse, [
            'csrf_protection' => false,
        ]);

        $form->submit([
            'content' => 'Valid content',
            'imageFile' => null,
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertNull($forumResponse->getImageFile());
    }

    public function testContentIsRequired(): void
    {
        $forumResponse = new ForumResponse();
        $form = $this->formFactory->create(ForumResponseType::class, $forumResponse, [
            'csrf_protection' => false,
        ]);

        $form->submit([
            'content' => '',
            'imageFile' => null,
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());

        $errors = $form->getErrors(true);
        $this->assertStringContainsString('Le contenu ne peut pas être vide.', $errors->current()->getMessage());
    }

    public function testSubmitOnlyContent(): void
    {
        $forumResponse = new ForumResponse();
        $form = $this->formFactory->create(ForumResponseType::class, $forumResponse, [
            'csrf_protection' => false,
        ]);

        $form->submit([
            'content' => 'Valid content',
            'imageFile' => null,
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('Valid content', $forumResponse->getContent());
        $this->assertNull($forumResponse->getImageFile());
    }

    public function testSubmitOnlyImageFile(): void
    {
        $forumResponse = new ForumResponse();
        $form = $this->formFactory->create(ForumResponseType::class, $forumResponse, [
            'csrf_protection' => false,
        ]);

        $tempFilePath = tempnam(sys_get_temp_dir(), 'test');
        // Create a valid 1x1 pixel JPEG image
        $image = imagecreatetruecolor(1, 1);
        imagejpeg($image, $tempFilePath);
        imagedestroy($image);

        $imageFile = new UploadedFile(
            $tempFilePath,
            'testfile.jpg',
            'image/jpeg',
            UPLOAD_ERR_OK,
            true
        );

        $form->submit([
            'content' => '', // Champ vide
            'imageFile' => $imageFile,
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid()); // Le contenu est requis, donc invalide
        $this->assertNull($forumResponse->getContent());
        $this->assertSame($imageFile, $forumResponse->getImageFile());

        unlink($tempFilePath);
    }
}
