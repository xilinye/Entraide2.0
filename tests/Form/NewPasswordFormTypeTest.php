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
            // Mot de passe trop court
            [
                'data' => [
                    'plainPassword' => [
                        'first' => 'short',
                        'second' => 'short'
                    ]
                ],
                'expectedErrors' => 2 // Length + Regex
            ],
            // Mots de passe non identiques
            [
                'data' => [
                    'plainPassword' => [
                        'first' => 'Password1!',
                        'second' => 'Different2!'
                    ]
                ],
                'expectedErrors' => 1 // Invalid_message
            ],
            // Champs vides
            [
                'data' => [
                    'plainPassword' => [
                        'first' => '',
                        'second' => ''
                    ]
                ],
                'expectedErrors' => 1 // NotBlank
            ],
            // Regex échoué (manque majuscule/chiffre/caractère spécial)
            [
                'data' => [
                    'plainPassword' => [
                        'first' => 'password',
                        'second' => 'password'
                    ]
                ],
                'expectedErrors' => 1 // Regex
            ],
            // Manque majuscule
            [
                'data' => [
                    'plainPassword' => [
                        'first' => 'secure123!',
                        'second' => 'secure123!'
                    ]
                ],
                'expectedErrors' => 1 // Regex
            ],
            // Manque chiffre
            [
                'data' => [
                    'plainPassword' => [
                        'first' => 'Securepass!',
                        'second' => 'Securepass!'
                    ]
                ],
                'expectedErrors' => 1 // Regex
            ],
            // Manque caractère spécial
            [
                'data' => [
                    'plainPassword' => [
                        'first' => 'SecurePass123',
                        'second' => 'SecurePass123'
                    ]
                ],
                'expectedErrors' => 1 // Regex
            ]
        ];

        foreach ($testCases as $case) {
            $form = $this->formFactory->create(NewPasswordFormType::class, null, [
                'csrf_protection' => false
            ]);
            $form->submit($case['data']);
            $this->assertFalse($form->isValid());
            $this->assertCount(
                $case['expectedErrors'],
                $form->getErrors(true),
                sprintf('Le scénario "%s" devrait générer %d erreur(s)', json_encode($case['data']), $case['expectedErrors'])
            );
        }
    }
}
