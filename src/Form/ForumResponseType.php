<?php

namespace App\Form;

use App\Entity\ForumResponse;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\{TextareaType, FileType};
use Symfony\Component\Validator\Constraints\{File, NotBlank};

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
                ],
                'required' => false,
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Nouvelle image',
                'required' => false,
                'mapped' => true
            ]);;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ForumResponse::class,
        ]);
    }
}
