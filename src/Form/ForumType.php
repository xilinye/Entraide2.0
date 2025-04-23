<?php

namespace App\Form;

use App\Entity\{Forum, Category};
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\{TextType, TextareaType, FileType};
use Symfony\Component\Validator\Constraints\File;

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
            ->add('imageFile', FileType::class, [
                'label' => 'Image (JPEG ou PNG)',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG ou PNG)',
                    ])
                ],
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
