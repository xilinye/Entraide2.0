<?php

namespace App\Tests\Form;

use App\Entity\Category;
use App\Entity\Skill;
use App\Form\SkillSelectionType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class SkillSelectionTypeTest extends KernelTestCase
{
    private FormInterface $form;

    protected function setUp(): void
    {
        self::bootKernel();
        $formFactory = self::getContainer()->get('form.factory');
        $this->form = $formFactory->create(SkillSelectionType::class, null, ['csrf_protection' => false]);
    }

    public function testFormFieldsAndTypes(): void
    {
        $form = $this->form;

        $this->assertTrue($form->has('category'));
        $this->assertTrue($form->has('skill'));

        $categoryConfig = $form->get('category')->getConfig();
        $this->assertEquals(Category::class, $categoryConfig->getOption('class'));
        $this->assertFalse($categoryConfig->getOption('required'));
        $this->assertFalse($categoryConfig->getOption('mapped'));

        $skillConfig = $form->get('skill')->getConfig();
        $this->assertEquals(Skill::class, $skillConfig->getOption('class'));
        $this->assertTrue($skillConfig->getOption('required'));
    }

    public function testSkillFieldConstraints(): void
    {
        $skillConfig = $this->form->get('skill')->getConfig();
        $constraints = $skillConfig->getOption('constraints');

        $this->assertCount(1, $constraints);
        $this->assertInstanceOf(NotBlank::class, $constraints[0]);
        $this->assertEquals('Veuillez sélectionner une compétence', $constraints[0]->message);
    }

    public function testConfigureOptions(): void
    {
        $resolver = new \Symfony\Component\OptionsResolver\OptionsResolver();
        $formType = new SkillSelectionType();
        $formType->configureOptions($resolver);

        $options = $resolver->resolve();
        $this->assertNull($options['data_class']);
        $this->assertNull($options['selected_category']);
        $this->assertTrue($options['required']);
        $this->assertEquals(['Default'], $options['validation_groups']);
    }

    public function testSubmitValidData(): void
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();

        $categoryName = 'TestCategory-' . uniqid();
        $skillName = 'TestSkill-' . uniqid();

        $category = (new Category())->setName($categoryName);
        $skill = (new Skill())->setName($skillName)->setCategory($category);

        $entityManager->persist($category);
        $entityManager->persist($skill);
        $entityManager->flush();
        $entityManager->clear();

        $category = $entityManager->getRepository(Category::class)->findOneBy(['name' => $categoryName]);
        $skill = $entityManager->getRepository(Skill::class)->findOneBy(['name' => $skillName]);

        $formFactory = self::getContainer()->get('form.factory');
        $form = $formFactory->create(SkillSelectionType::class, null, [
            'selected_category' => $category,
            'csrf_protection' => false,
        ]);

        $form->submit([
            'category' => $category->getId(),
            'skill' => $skill->getId(),
        ]);

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isValid(), 'Form should be valid with correct skill ID');

        $submittedSkill = $form->get('skill')->getData();
        $this->assertInstanceOf(Skill::class, $submittedSkill);
        $this->assertEquals($skill->getId(), $submittedSkill->getId());
    }

    public function testSubmitInvalidData(): void
    {
        $form = $this->form;
        $form->submit([
            'category' => null,
            'skill' => null,
        ]);

        $this->assertTrue($form->isSubmitted());
        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('skill')->getErrors());
    }
}
