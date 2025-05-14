<?php

namespace App\Form;

use App\Entity\Event;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\{DateTimeType, IntegerType, FileType};
use Symfony\Component\Validator\Constraints\{File, Range, NotBlank, NotNull};

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', null, [
                'label' => 'Titre',
                'constraints' => [
                    new NotBlank(['message' => 'Le titre est obligatoire'])
                ]
            ])
            ->add('description', null, [
                'label' => 'Description'
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Nouvelle image',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'maxSizeMessage' => 'Sa taille ne doit pas dépasser 5 MB.'
                    ])
                ]
            ])
            ->add('startDate', DateTimeType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'html5' => false,
                'attr' => [
                    'class' => 'datetimepicker',
                    'data-input' => true
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La date de début est obligatoire'])
                ],
            ])
            ->add('endDate', DateTimeType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'html5' => false,
                'attr' => [
                    'class' => 'datetimepicker',
                    'data-input' => true
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La date de fin est obligatoire'])
                ],
            ])
            ->add('location', null, [
                'label' => 'Lieu',
                'constraints' => [
                    new NotBlank(['message' => 'Le lieu est obligatoire'])
                ]
            ])
            ->add(
                'maxAttendees',
                IntegerType::class
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
            'csrf_protection' => $this->isCsrfEnabled(),
        ]);
    }

    private function isCsrfEnabled(): bool
    {
        // Désactive CSRF uniquement en environnement test
        return $_ENV['APP_ENV'] !== 'test';
    }
}
