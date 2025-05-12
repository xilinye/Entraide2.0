<?php

namespace App\Tests\Form;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Form\RegistrationFormType;
use App\Entity\User;
use Symfony\Component\Form\FormFactoryInterface;

class RegistrationFormTypeTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->formFactory = self::getContainer()->get('form.factory');
    }

    public function testValidSubmission(): void
    {
        $form = $this->formFactory->create(RegistrationFormType::class, new User(), [
            'csrf_protection' => false
        ]);

        $formData = [
            'pseudo' => 'ValidUser123',
            'email' => 'valid@example.com',
            'plainPassword' => [
                'first' => 'SecurePass123!',
                'second' => 'SecurePass123!'
            ],
            'agreeTerms' => true
        ];

        $form->submit($formData);

        $this->assertTrue($form->isValid());
        $this->assertTrue($form->isSynchronized());
    }

    public function testInvalidDataScenarios(): void
    {
        $testCases = [
            [
                'data' => [
                    'pseudo' => 'A', // 2 erreurs (longueur + regex)
                    'email' => 'invalid', // 1 erreur
                    'plainPassword' => [
                        'first' => 'weak', // 2 erreurs (longueur + regex)
                        'second' => 'weak'  // 1 erreur (doit correspondre si champs différents)
                    ],
                    'agreeTerms' => false // 1 erreur
                ],
                'expectedErrors' => 7 // Total des erreurs
            ]
        ];

        foreach ($testCases as $case) {
            $form = $this->formFactory->create(RegistrationFormType::class, new User(), [
                'csrf_protection' => false
            ]);

            $form->submit($case['data']);

            $this->assertFalse($form->isValid());
            $this->assertCount($case['expectedErrors'], $form->getErrors(true));
        }
    }
}
