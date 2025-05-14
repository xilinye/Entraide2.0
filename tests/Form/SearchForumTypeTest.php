<?php

namespace App\Tests\Form;

use App\Entity\Category;
use App\Form\SearchForumType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityRepository;

class SearchForumTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        // Mock ClassMetadata
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('getName')->willReturn(Category::class);
        $classMetadata->method('getIdentifierFieldNames')->willReturn(['id']);

        // Mock QueryBuilder
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);

        // Mock Repository
        $mockRepository = $this->createMock(EntityRepository::class);
        $mockRepository->method('createQueryBuilder')
            ->willReturn($mockQueryBuilder);

        // Mock EntityManager
        $mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $mockEntityManager->method('getClassMetadata')
            ->with(Category::class)
            ->willReturn($classMetadata);
        $mockEntityManager->method('getRepository')
            ->with(Category::class)
            ->willReturn($mockRepository);

        // Mock ManagerRegistry
        /** @var ManagerRegistry&MockObject $mockRegistry */
        $mockRegistry = $this->createMock(ManagerRegistry::class);
        $mockRegistry->method('getManagerForClass')
            ->with(Category::class)
            ->willReturn($mockEntityManager);

        // Create EntityType with the mocked registry
        $entityType = new EntityType($mockRegistry);

        return [
            new PreloadedExtension([$entityType], []),
        ];
    }

    public function testBuildForm(): void
    {
        $form = $this->factory->create(SearchForumType::class);

        $this->assertTrue($form->has('query'));
        $this->assertTrue($form->has('category'));

        $queryField = $form->get('query');
        $this->assertFalse($queryField->isRequired());
        $this->assertEquals(
            ['placeholder' => 'Rechercher dans le forum...'],
            $queryField->getConfig()->getOption('attr')
        );

        $categoryField = $form->get('category');
        $this->assertInstanceOf(EntityType::class, $categoryField->getConfig()->getType()->getInnerType());
        $this->assertEquals(Category::class, $categoryField->getConfig()->getOption('class'));
        $this->assertFalse($categoryField->isRequired());
        $this->assertEquals('name', $categoryField->getConfig()->getOption('choice_label'));
        $this->assertEquals('Toutes les catégories', $categoryField->getConfig()->getOption('placeholder'));
    }

    public function testConfigureOptions(): void
    {
        $resolver = new OptionsResolver();
        $formType = new SearchForumType();
        $formType->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertArrayHasKey('method', $options);
        $this->assertEquals('GET', $options['method']);
        $this->assertArrayHasKey('csrf_protection', $options);
        $this->assertFalse($options['csrf_protection']);
    }
}
