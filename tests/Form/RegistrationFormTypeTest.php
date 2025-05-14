<?php

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormInterface;

class RegistrationFormTypeTest extends KernelTestCase
{
    private FormInterface $form;

    protected function setUp(): void
    {
        self::bootKernel();
        $formFactory = self::getContainer()->get('form.factory');
        $this->form = $formFactory->create(RegistrationFormType::class, new User(), [
            'csrf_protection' => false
        ]);
    }

    private function assertFormHasError(FormInterface $form, string $fieldName, string $expectedError): void
    {
        $errors = $form->get($fieldName)->getErrors();
        foreach ($errors as $error) {
            if ($error->getMessage() === $expectedError) {
                $this->assertTrue(true);
                return;
            }
        }
        $this->fail("Expected error '$expectedError' not found for field $fieldName");
    }

    public function testValidSubmission(): void
    {
        $formData = [
            'pseudo' => 'ValidUser_123',
            'email' => 'valid@example.com',
            'plainPassword' => [
                'first' => 'SecurePass123!',
                'second' => 'SecurePass123!'
            ],
            'agreeTerms' => true
        ];

        $this->form->submit($formData);
        $this->assertTrue($this->form->isValid());
    }

    public function provideInvalidCases(): iterable
    {
        yield 'Pseudo trop court' => [
            ['pseudo' => 'A'],
            'pseudo',
            'Votre pseudonyme doit contenir au moins 3 caractères'
        ];

        yield 'Pseudo trop long' => [
            ['pseudo' => str_repeat('a', 51)],
            'pseudo',
            'Votre pseudonyme ne peut pas dépasser 50 caractères'
        ];

        yield 'Pseudo avec caractères invalides' => [
            ['pseudo' => 'user@test'],
            'pseudo',
            'Seuls les lettres, chiffres et underscores sont autorisés'
        ];

        yield 'Email invalide' => [
            ['email' => 'invalid-email'],
            'email',
            'Cette valeur n\'est pas une adresse email valide.'
        ];

        yield 'Password trop court' => [
            ['plainPassword' => ['first' => 'Short1!', 'second' => 'Short1!']],
            'plainPassword.first',
            'Votre mot de passe doit contenir au moins 8 caractères'
        ];

        yield 'Password sans majuscule' => [
            ['plainPassword' => ['first' => 'lowercase123!', 'second' => 'lowercase123!']],
            'plainPassword.first',
            'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial'
        ];

        yield 'Password sans caractère spécial' => [
            ['plainPassword' => ['first' => 'Password123', 'second' => 'Password123']],
            'plainPassword.first', // <--
            'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial'
        ];

        yield 'Passwords non identiques' => [
            ['plainPassword' => ['first' => 'Pass123!', 'second' => 'Wrong123!']],
            'plainPassword',
            'Les mots de passe doivent correspondre.'
        ];

        yield 'CGU non acceptées' => [
            ['agreeTerms' => false],
            'agreeTerms',
            'Vous devez accepter les CGU et la politique de confidentialité.'
        ];
    }

    /**
     * @dataProvider provideInvalidCases
     */
    public function testInvalidSubmissions(array $formData, string $fieldName, string $expectedError): void
    {
        $defaultData = [
            'pseudo' => 'ValidUser',
            'email' => 'valid@example.com',
            'plainPassword' => [
                'first' => 'ValidPass123!',
                'second' => 'ValidPass123!'
            ],
            'agreeTerms' => true
        ];

        $mergedData = array_replace_recursive($defaultData, $formData);
        $this->form->submit($mergedData);

        $this->assertFormHasError($this->form, $fieldName, $expectedError);
    }
}
