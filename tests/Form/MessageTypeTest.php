<?php

namespace App\Tests\Form;


use App\Entity\Message;
use App\Form\MessageType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Validator\Validation;

class MessageTypeTest extends TypeTestCase
{
    public function testIncludeTitleOption(): void
    {
        $form = $this->factory->create(MessageType::class, null, ['include_title' => true]);
        $view = $form->createView();
        $this->assertArrayHasKey('title', $view->children, 'Title field should be present when include_title is true.');
        $this->assertArrayHasKey('content', $view->children, 'Content field should always be present.');
        $this->assertArrayHasKey('imageFile', $view->children, 'imageFile field should always be present.');

        $form = $this->factory->create(MessageType::class, null, ['include_title' => false]);
        $view = $form->createView();
        $this->assertArrayNotHasKey('title', $view->children, 'Title field should not be present when include_title is false.');
        $this->assertArrayHasKey('content', $view->children);
        $this->assertArrayHasKey('imageFile', $view->children);
    }

    protected function getExtensions()
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return [
            new HttpFoundationExtension(),
            new ValidatorExtension($validator),
        ];
    }
    public function testSubmitValidData(): void
    {
        $tempFilePath = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFilePath, 'Dummy content');

        $uploadedFile = new UploadedFile(
            $tempFilePath,
            'dummy.txt',
            'text/plain',
            null,
            true
        );

        $formData = [
            'title' => 'Test Title',
            'content' => 'Test content',
            'imageFile' => $uploadedFile,
        ];

        $form = $this->factory->create(MessageType::class, null, ['include_title' => true]);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());

        /** @var Message $message */
        $message = $form->getData();

        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals('Test Title', $message->getTitle());
        $this->assertEquals('Test content', $message->getContent());
        $this->assertSame($uploadedFile, $message->getImageFile());

        unlink($tempFilePath);
    }

    public function testConfigureOptions(): void
    {
        $resolver = new OptionsResolver();
        $formType = new MessageType();
        $formType->configureOptions($resolver);

        $options = $resolver->resolve();
        $this->assertEquals(Message::class, $options['data_class'], 'data_class should be set to Message entity.');
        $this->assertTrue($options['include_title'], 'include_title should default to true.');
    }

    public function testValidationConstraints(): void
    {
        $formData1 = [
            'title' => '',
            'content' => 'Test content',
            'imageFile' => null,
        ];
        $form1 = $this->factory->create(MessageType::class, null, ['include_title' => true]);
        $form1->submit($formData1);
        $this->assertFalse($form1->isValid());
        $errors1 = $form1->get('title')->getErrors();
        $this->assertCount(1, $errors1);
        $this->assertEquals('Le titre ne peut pas être vide.', $errors1[0]->getMessage());

        $formData2 = [
            'title' => 'Valid Title',
            'content' => '',
            'imageFile' => null,
        ];
        $form2 = $this->factory->create(MessageType::class, null, ['include_title' => true]);
        $form2->submit($formData2);
        $this->assertFalse($form2->isValid());
        $errors2 = $form2->get('content')->getErrors();
        $this->assertCount(1, $errors2);
        $this->assertEquals('Le contenu ne peut pas être vide.', $errors2[0]->getMessage());
    }

    public function testRequiredFields(): void
    {
        $form = $this->factory->create(MessageType::class, null, ['include_title' => true]);

        $formData = [
            'title' => 'Test Title',
            'content' => '',
            'imageFile' => null,
        ];
        $form->submit($formData);
        $this->assertFalse($form->isValid());
        $errors = $form->get('content')->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals('Le contenu ne peut pas être vide.', $errors[0]->getMessage());
    }

    public function testImageFileNotRequired(): void
    {
        $tempFilePath = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFilePath, 'Dummy content');

        $uploadedFile = new UploadedFile(
            $tempFilePath,
            'dummy.txt',
            'text/plain',
            null,
            true
        );

        // Test avec un fichier téléchargé
        $formDataWithFile = [
            'title' => 'Test Title',
            'content' => 'Test content',
            'imageFile' => $uploadedFile,
        ];
        $formWithFile = $this->factory->create(MessageType::class, null, ['include_title' => true]);
        $formWithFile->submit($formDataWithFile);
        $this->assertTrue($formWithFile->isSynchronized());

        // Test sans fichier téléchargé
        $formDataWithoutFile = [
            'title' => 'Test Title',
            'content' => 'Test content',
            'imageFile' => null,
        ];
        $formWithoutFile = $this->factory->create(MessageType::class, null, ['include_title' => true]);
        $formWithoutFile->submit($formDataWithoutFile);
        $this->assertTrue($formWithoutFile->isSynchronized());

        unlink($tempFilePath);
    }
}
