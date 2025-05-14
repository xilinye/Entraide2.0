<?php

namespace App\Tests\Form;

use App\Entity\Category;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CategoryFormTypeTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    // Test de soumission valide
    public function testSubmitValidData()
    {
        $validNames = [
            "Technologies", // Cas général
            "AB",           // 2 caractères (min)
            str_repeat('a', 50) // 50 caractères (max)
        ];

        foreach ($validNames as $name) {
            $category = new Category();
            $category->setName($name);

            $errors = $this->validator->validate($category);
            $this->assertCount(0, $errors, "Échec avec le nom : '$name'");
        }
    }

    // Test de données invalides
    /**
     * @dataProvider invalidNameProvider
     */
    public function testSubmitInvalidData(string $invalidName, string $errorMessage)
    {
        $category = new Category();
        $category->setName($invalidName);

        $errors = $this->validator->validate($category);
        $this->assertGreaterThan(0, count($errors));

        $this->assertStringContainsString($errorMessage, $errors[0]->getMessage());
    }

    // Scénarios de test invalides
    public function invalidNameProvider(): array
    {
        return [
            'Nom vide' => ['', 'Le nom de la catégorie est obligatoire'],
            'Trop court (1 caractère)' => ['A', 'au moins 2 caractères'],
            'Trop long (51 caractères)' => [str_repeat('a', 51), 'pas dépasser 50 caractères'],
        ];
    }
}
