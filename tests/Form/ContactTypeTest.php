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
            ->enableAttributeMapping()
            ->getValidator();

        return [new ValidatorExtension($validator)];
    }

    public function testChampsDuFormulaire(): void
    {
        $form = $this->factory->create(ContactType::class);

        $this->assertTrue($form->has('name'));
        $this->assertTrue($form->has('email'));
        $this->assertTrue($form->has('subject'));
        $this->assertTrue($form->has('message'));
    }

    public function testValidationAvecDonneesVides(): void
    {
        $form = $this->factory->create(ContactType::class);
        $form->submit([]);

        $this->assertFalse($form->isValid());
        $this->assertCount(4, $form->getErrors(true));
    }

    public function testEmailInvalide(): void
    {
        $form = $this->factory->create(ContactType::class);
        $form->submit([
            'name' => 'Valid Name',
            'email' => 'invalid-email',
            'subject' => 'Valid Subject',
            'message' => 'Valid message content'
        ]);

        $this->assertFalse($form->isValid());
        $this->assertStringContainsString(
            'L\'email "invalid-email" n\'est pas valide.',
            (string) $form->getErrors(true)
        );
    }

    public function testLongueurMaximale(): void
    {
        // Test nom trop long
        $form = $this->factory->create(ContactType::class);
        $form->submit([
            'name' => str_repeat('a', 51),
            'email' => 'valid@example.com',
            'subject' => 'Valid Subject',
            'message' => 'Valid message'
        ]);
        $this->assertStringContainsString('50 caractères', (string) $form->getErrors(true));

        // Test sujet trop long
        $form = $this->factory->create(ContactType::class);
        $form->submit([
            'name' => 'Valid Name',
            'email' => 'valid@example.com',
            'subject' => str_repeat('a', 101),
            'message' => 'Valid message'
        ]);
        $this->assertStringContainsString('100 caractères', (string) $form->getErrors(true));

        // Test message trop long
        $form = $this->factory->create(ContactType::class);
        $form->submit([
            'name' => 'Valid Name',
            'email' => 'valid@example.com',
            'subject' => 'Valid Subject',
            'message' => str_repeat('a', 1001)
        ]);
        $this->assertStringContainsString('1000 caractères', (string) $form->getErrors(true));
    }

    public function testSoumissionValide(): void
    {
        $donneesValides = [
            'name' => 'Jean Dupont',
            'email' => 'jean.dupont@exemple.com',
            'subject' => 'Demande de contact',
            'message' => 'Ceci est un message de test valide.'
        ];

        $form = $this->factory->create(ContactType::class);
        $form->submit($donneesValides);

        $this->assertTrue($form->isValid());
        $this->assertEquals($donneesValides, $form->getData());
    }

    public function testLongueursMaximalesAcceptees(): void
    {
        $donnees = [
            'name' => str_repeat('a', 50), // 50 caractères exactement
            'email' => 'a@a.fr',
            'subject' => str_repeat('b', 100), // 100 caractères
            'message' => str_repeat('c', 1000) // 1000 caractères
        ];

        $form = $this->factory->create(ContactType::class);
        $form->submit($donnees);

        $this->assertTrue($form->isValid()); // Doit passer
    }

    public function testErreurSiNomManquant(): void
    {
        $form = $this->factory->create(ContactType::class);
        $form->submit([
            'email' => 'test@test.com', // Rempli correctement
            // 'name' manquant
            'subject' => 'Sujet',
            'message' => 'Message'
        ]);

        $this->assertFalse($form->isValid());
    }
}
