<?php

namespace App\Tests\Form;

use App\Entity\Category;
use App\Form\SkillFormType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class SkillFormTypeTest extends KernelTestCase
{
    private $formFactory;
    private $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->formFactory = self::getContainer()->get('form.factory');
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();
        $this->entityManager->close();
        parent::tearDown();
    }

    public function testConstructionFormulaire()
    {
        // Vérifie la structure de base du formulaire
        $form = $this->formFactory->create(SkillFormType::class);

        $this->assertTrue($form->has('name'), 'Le champ "name" devrait exister');
        $this->assertTrue($form->has('category'), 'Le champ "category" devrait exister');

        // Vérifie le type des champs
        $nameField = $form->get('name')->getConfig()->getType()->getInnerType();
        $this->assertInstanceOf(TextType::class, $nameField, 'Le champ "name" devrait être un TextType');

        $categoryField = $form->get('category')->getConfig()->getType()->getInnerType();
        $this->assertInstanceOf(EntityType::class, $categoryField, 'Le champ "category" devrait être un EntityType');
        $this->assertEquals(Category::class, $form->get('category')->getConfig()->getOption('class'), 'L\'EntityType devrait être lié à la classe Category');
        $this->assertEquals('Choisissez une catégorie', $form->get('category')->getConfig()->getOption('placeholder'), 'Le placeholder devrait être correct');
    }

    public function testSoumissionDonneesValides()
    {
        // Crée une catégorie de test
        $category = new Category();
        $category->setName('Développement Web');
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        // Soumet des données valides
        $form = $this->formFactory->create(SkillFormType::class, null, ['csrf_protection' => false]);
        $form->submit([
            'name' => 'Symfony',
            'category' => $category->getId(),
        ]);

        $this->assertTrue($form->isSynchronized(), 'Le formulaire devrait être synchronisé');
        $this->assertTrue($form->isValid(), 'Le formulaire devrait être valide');

        // Vérifie les données transformées
        $data = $form->getData();
        $this->assertEquals('Symfony', $data['name'], 'Le nom devrait correspondre');
        $this->assertInstanceOf(Category::class, $data['category'], 'La catégorie devrait être une instance de Category');
        $this->assertEquals($category->getId(), $data['category']->getId(), 'L\'ID de la catégorie devrait correspondre');
    }

    public function testSoumissionDonneesInvalides()
    {
        // Soumet des données vides
        $form = $this->formFactory->create(SkillFormType::class, null, ['csrf_protection' => false]);
        $form->submit([
            'name' => '',
            'category' => null,
        ]);

        $this->assertTrue($form->isSynchronized(), 'Le formulaire devrait être synchronisé');
        $this->assertFalse($form->isValid(), 'Le formulaire devrait être invalide');

        // Vérifie les erreurs de validation
        $this->assertNotEmpty($form->get('name')->getErrors(), 'Le champ "name" devrait avoir une erreur');
        $this->assertNotEmpty($form->get('category')->getErrors(), 'Le champ "category" devrait avoir une erreur');
    }

    public function testProtectionCsrf()
    {
        // Teste la protection CSRF
        $form = $this->formFactory->create(SkillFormType::class);
        $form->submit([
            'name' => 'React',
            'category' => 1, // Supposons qu'une catégorie existe
        ]);

        $this->assertFalse($form->isValid(), 'Le formulaire devrait être invalide sans CSRF');
        $this->assertStringContainsString('CSRF', $form->getErrors()[0]->getMessage(), 'Devrait contenir une erreur CSRF');
    }
}
