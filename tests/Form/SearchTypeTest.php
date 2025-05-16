<?php

namespace App\Tests\Form;

use App\Entity\{Category, Skill};
use App\Form\SearchType;
use App\Repository\SkillRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SearchTypeTest extends KernelTestCase
{
    public function testChampsEtConfigurationDuFormulaire(): void
    {
        self::bootKernel();
        $formFactory = self::getContainer()->get('form.factory');
        $form = $formFactory->create(SearchType::class);

        $this->assertTrue($form->has('category'));
        $this->assertTrue($form->has('skill'));
    }

    public function testOptionsDuFormulaire(): void
    {
        self::bootKernel();
        $formFactory = self::getContainer()->get('form.factory');
        $form = $formFactory->create(SearchType::class);

        $this->assertEquals('GET', $form->getConfig()->getMethod());
        $this->assertFalse($form->getConfig()->getOption('csrf_protection'));
    }

    public function testConfigurationDesOptions(): void
    {
        $resolver = new OptionsResolver();
        $formType = new SearchType();
        $formType->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertEquals('GET', $options['method']);
        $this->assertFalse($options['csrf_protection']);
        $this->assertNull($options['category']);
    }

    public function testQueryBuilderSansCategorie(): void
    {
        /** @var FormBuilderInterface&MockObject $formBuilder */
        $formBuilder = $this->createMock(FormBuilderInterface::class);

        $formBuilder->method('add')->willReturnCallback(
            function ($name, $type, $options) use ($formBuilder) {
                if ($name === 'skill') {
                    $this->validerQueryBuilder(
                        $options['query_builder'],
                        null
                    );
                }
                return $formBuilder;
            }
        );

        $formType = new SearchType();
        $formType->buildForm($formBuilder, ['category' => null]);
    }

    public function testQueryBuilderAvecCategorie(): void
    {
        $categorie = new Category();

        /** @var FormBuilderInterface&MockObject $formBuilder */
        $formBuilder = $this->createMock(FormBuilderInterface::class);

        $formBuilder->method('add')->willReturnCallback(
            function ($name, $type, $options) use ($formBuilder, $categorie) {
                if ($name === 'skill') {
                    $this->validerQueryBuilder(
                        $options['query_builder'],
                        $categorie
                    );
                }
                return $formBuilder;
            }
        );

        $formType = new SearchType();
        $formType->buildForm($formBuilder, ['category' => $categorie]);
    }

    private function validerQueryBuilder(callable $queryBuilderClosure, ?Category $categorie): void
    {
        /** @var SkillRepository&MockObject $repository */
        $repository = $this->createMock(SkillRepository::class);
        /** @var QueryBuilder&MockObject $queryBuilder */
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('s')
            ->willReturn($queryBuilder);

        if ($categorie !== null) {
            $queryBuilder->expects($this->once())
                ->method('andWhere')
                ->with('s.category = :category')
                ->willReturnSelf();
            $queryBuilder->expects($this->once())
                ->method('setParameter')
                ->with('category', $categorie)
                ->willReturnSelf();
        } else {
            $queryBuilder->expects($this->never())
                ->method('andWhere');
            $queryBuilder->expects($this->never())
                ->method('setParameter');
        }

        $queryBuilderClosure($repository);
    }

    public function testConfigurationChampCategory(): void
    {
        self::bootKernel();
        $form = self::getContainer()->get('form.factory')->create(SearchType::class);

        $champ = $form->get('category');
        $options = $champ->getConfig()->getOptions();

        $this->assertFalse($options['required']); // Champ non obligatoire
        $this->assertEquals(Category::class, $options['class']); // Classe Entity correcte
        $this->assertEquals('Toutes les catégories', $options['placeholder']); // Placeholder
    }

    public function testConfigurationChampSkill(): void
    {
        self::bootKernel();
        $form = self::getContainer()->get('form.factory')->create(SearchType::class);

        $champ = $form->get('skill');
        $options = $champ->getConfig()->getOptions();

        $this->assertFalse($options['required']); // Champ non obligatoire
        $this->assertEquals(Skill::class, $options['class']); // Classe Entity correcte
        $this->assertEquals('Toutes les compétences', $options['placeholder']); // Placeholder
    }

    public function testSoumissionAvecCategorie(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager(); // Récupère l'EntityManager

        // Crée les entités
        $category = (new Category())->setName('Dev Web');
        $skill = (new Skill())->setName('PHP')->setCategory($category);

        // Persiste et flush les entités
        $em->persist($category);
        $em->persist($skill);
        $em->flush();

        // Crée le formulaire avec l'option 'category'
        $form = self::getContainer()->get('form.factory')->create(SearchType::class, null, [
            'category' => $category
        ]);

        // Soumet les IDs des entités persistées
        $form->submit([
            'category' => $category->getId(),
            'skill' => $skill->getId()
        ]);

        // Vérifications
        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($category, $form->get('category')->getData());
        $this->assertEquals($skill, $form->get('skill')->getData());
    }
}
