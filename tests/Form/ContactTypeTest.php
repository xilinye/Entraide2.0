<?php

namespace App\Tests\Form;

use App\Form\ContactType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class ContactTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping(true)
            ->getValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }

    public function testFormFields(): void
    {
        $form = $this->factory->create(ContactType::class);

        $this->assertTrue($form->has('name'));
        $this->assertTrue($form->has('email'));
        $this->assertTrue($form->has('subject'));
        $this->assertTrue($form->has('message'));
    }

    public function testFormValidation(): void
    {
        $form = $this->factory->create(ContactType::class);
        $form->submit([
            'name' => '',
            'email' => '',
            'subject' => '',
            'message' => '',
        ]);

        $this->assertFalse($form->isValid());
        $errors = $form->getErrors(true);
        $this->assertCount(4, $errors); // Each field has a NotBlank constraint
    }

    public function testValidDataSubmission(): void
    {
        $formData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Inquiry',
            'message' => 'Valid message content.',
        ];

        $form = $this->factory->create(ContactType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($formData, $form->getData());
    }
}
