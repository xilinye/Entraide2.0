<?php

namespace App\Form;

use App\Entity\BlogPost;
use Symfony\Component\Form\{AbstractType,FormBuilderInterface};
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\{TextType,TextareaType};

class BlogPostFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Entrez un titre percutant']
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'attr' => ['rows' => 12, 'placeholder' => 'Rédigez votre article ici...']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BlogPost::class,
        ]);
    }
}
