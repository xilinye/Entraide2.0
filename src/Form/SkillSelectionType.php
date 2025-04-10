<?php

namespace App\Form;

use App\Entity\Skill;
use App\Entity\Category;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class SkillSelectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Sélectionnez une catégorie',
                'required' => false,
                'mapped' => false,
                'data' => $options['selected_category'],
                'attr' => [
                    'class' => 'form-select',
                    'data-controller' => 'category-selector',
                    'data-action' => 'change->category-selector#changeCategory'
                ],
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->orderBy('c.name', 'ASC');
                }
            ])
            ->add('skill', EntityType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner une compétence'])
                ],
                'class' => Skill::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er) use ($options) {
                    $qb = $er->createQueryBuilder('s')
                        ->innerJoin('s.category', 'c')
                        ->orderBy('c.name', 'ASC')
                        ->addOrderBy('s.name', 'ASC');

                    if ($options['selected_category']) {
                        $catId = $options['selected_category'] instanceof Category
                            ? $options['selected_category']->getId()
                            : (int)$options['selected_category'];
                        $qb->andWhere('c.id = :category')
                            ->setParameter('category', $catId);
                    } else {
                        $qb->andWhere('1 = 0');
                    }

                    return $qb;
                },
                'attr' => [
                    'class' => 'form-select'
                ],
                'placeholder' => $options['selected_category'] ? 'Sélectionnez une compétence...' : 'Veuillez d\'abord choisir une catégorie',
                'invalid_message' => 'Veuillez sélectionner une compétence valide',
                'required' => $options['required'],
                'choice_attr' => function (Skill $skill) {
                    return ['data-category-id' => $skill->getCategory()?->getId()];
                }
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'selected_category' => null,
            'required' => true,
            'validation_groups' => ['Default'],
            'attr' => [
                'novalidate' => 'novalidate',
                'data-controller' => 'skill-form'
            ]
        ]);

        $resolver->setAllowedTypes('selected_category', ['null', 'int', 'string', Category::class]);
    }

    public function getBlockPrefix(): string
    {
        return 'skill_selection';
    }
}
