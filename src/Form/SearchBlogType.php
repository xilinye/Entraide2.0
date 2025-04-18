<?php

namespace App\Form;

use Symfony\Component\Form\{AbstractType,FormBuilderInterface};
use Symfony\Component\Form\Extension\Core\Type\SearchType as SymfonySearchType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchBlogType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('query', SymfonySearchType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Rechercher un article...',
                    'class' => 'form-control-lg'
                ],
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false
        ]);
    }
}
