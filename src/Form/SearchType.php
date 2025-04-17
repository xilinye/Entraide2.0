<?php

namespace App\Form;

use App\Entity\{Category, Skill};
use App\Repository\SkillRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $category = $options['category'] ?? null;

        $builder
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Toutes les catégories',
                'required' => false
            ])
            ->add('skill', EntityType::class, [
                'class' => Skill::class,
                'choice_label' => 'name',
                'placeholder' => 'Toutes les compétences',
                'required' => false,
                'query_builder' => function (SkillRepository $skillRepository) use ($category) {
                    $qb = $skillRepository->createQueryBuilder('s');

                    if ($category) {
                        $qb->andWhere('s.category = :category')
                            ->setParameter('category', $category);
                    }

                    return $qb;
                },
            ]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false,
            'category' => null // Définit explicitement l'option
        ]);

        // Autorise l'option 'category' pour le formulaire
        $resolver->setDefined(['category']);
        $resolver->setAllowedTypes('category', ['null', Category::class]);
    }
}
