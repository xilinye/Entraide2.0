<?php

namespace App\Form;

use App\Entity\ForumResponse;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\{TextareaType, FileType};

class ForumResponseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Votre réponse...'
                ]
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Ajouter une image',
                'required' => false,
                'mapped' => true,
                'attr' => [
                    'accept' => 'image/jpeg,image/png'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ForumResponse::class,
        ]);
    }
}
