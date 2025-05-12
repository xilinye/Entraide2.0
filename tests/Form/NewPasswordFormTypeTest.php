<?php

namespace App\Tests\Form;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Form\NewPasswordFormType;
use Symfony\Component\Form\FormFactoryInterface;

class NewPasswordFormTypeTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->formFactory = self::getContainer()->get('form.factory');
    }

    public function testValidPasswordSubmission(): void
    {
        $form = $this->formFactory->create(NewPasswordFormType::class, null, [
            'csrf_protection' => false
        ]);

        $form->submit([
            'plainPassword' => [
                'first' => 'NewSecurePass123!',
                'second' => 'NewSecurePass123!'
            ]
        ]);

        $this->assertTrue($form->isValid());
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
            $form = $this->formFactory->create(NewPasswordFormType::class, null, [
                'csrf_protection' => false
            ]);
            $form->submit($case['data']);
            $this->assertFalse($form->isValid());
            $this->assertCount($case['expectedErrors'], $form->getErrors(true));
        }
    }
}
