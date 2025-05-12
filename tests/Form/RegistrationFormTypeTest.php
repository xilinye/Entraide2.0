<?php

namespace App\Tests\Form;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Form\RegistrationFormType;
use App\Entity\User;
use Symfony\Component\Form\FormInterface;

class RegistrationFormTypeTest extends KernelTestCase
{
    private FormInterface $form;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $formFactory = self::getContainer()->get('form.factory');
        $this->user = new User();
        $this->form = $formFactory->create(RegistrationFormType::class, $this->user);
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
        $this->assertTrue($this->form->isSynchronized());
        $this->assertEquals($this->user, $this->form->getData());
    }

    public function testInvalidDataScenarios(): void
    {
        $testCases = [
            [
                'data' => [
                    'pseudo' => 'A', // Too short
                    'email' => 'invalid-email',
                    'plainPassword' => ['first' => 'weak', 'second' => 'weak'],
                    'agreeTerms' => false
                ],
                'expectedErrors' => 4
            ],
            [
                'data' => [
                    'pseudo' => 'ValidName',
                    'email' => 'valid@email.com',
                    'plainPassword' => ['first' => 'NoSpecialChar1', 'second' => 'NoSpecialChar1'],
                    'agreeTerms' => true
                ],
                'expectedErrors' => 1 // Missing special character
            ]
        ];

        foreach ($testCases as $case) {
            $this->form->submit($case['data']);
            $this->assertFalse($this->form->isValid());
            $this->assertCount($case['expectedErrors'], $this->form->getErrors(true));
        }
    }
}
