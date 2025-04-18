<?php

namespace App\Form;

use App\Entity\Forum;
use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class ForumType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de la discussion',
                'attr' => ['placeholder' => 'Entrez un titre clair et descriptif']
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu détaillé',
                'attr' => [
                    'rows' => 8,
                    'placeholder' => 'Décrivez votre problème ou question en détail...'
                ]
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'label' => 'Catégorie',
                'required' => false,
                'placeholder' => 'Choisir une catégorie (optionnel)',
                'choice_label' => 'name',
                'attr' => ['class' => 'select2']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Forum::class,
        ]);
    }
}
