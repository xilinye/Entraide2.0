<?php

namespace App\Tests\Form;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Form\NewPasswordFormType;
use Symfony\Component\Form\FormInterface;

class NewPasswordFormTypeTest extends KernelTestCase
{
    private FormInterface $form;

    protected function setUp(): void
    {
        self::bootKernel();
        $formFactory = self::getContainer()->get('form.factory');
        $this->form = $formFactory->create(NewPasswordFormType::class);
    }

    public function testValidPasswordSubmission(): void
    {
        $this->form->submit([
            'plainPassword' => [
                'first' => 'NewSecurePass123!',
                'second' => 'NewSecurePass123!'
            ]
        ]);

        $this->assertTrue($this->form->isValid());
        $this->assertTrue($this->form->isSynchronized());
    }

    public function testInvalidPasswordScenarios(): void
    {
        $testCases = [
            [
                'data' => [
                    'plainPassword' => [
                        'first' => 'short',
                        'second' => 'short'
                    ]
                ],
                'expectedErrors' => 2 // Length + regex
            ],
            [
                'data' => [
                    'plainPassword' => [
                        'first' => 'Password1!',
                        'second' => 'Different2!'
                    ]
                ],
                'expectedErrors' => 1 // Mismatch
            ]
        ];

        foreach ($testCases as $case) {
            $this->form->submit($case['data']);
            $this->assertFalse($this->form->isValid());
            $this->assertCount($case['expectedErrors'], $this->form->getErrors(true));
        }
    }
}
