<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\{NotBlank, Length};
use Symfony\Component\Form\Extension\Core\Type\{TextType, EmailType, TextareaType};
use Symfony\Component\Validator\Constraints\Email;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Votre nom',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 50, 'maxMessage' => 'Le nom ne doit pas dépasser {{ limit }} caractères.']),
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Votre email',
                'constraints' => [
                    new NotBlank(),
                    new Email(['message' => 'L\'email {{ value }} n\'est pas valide.']),
                ]
            ])
            ->add('subject', TextType::class, [
                'label' => 'Sujet',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 100, 'maxMessage' => 'Le sujet ne doit pas dépasser {{ limit }} caractères.']),
                ]
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Votre message',
                'attr' => ['rows' => 6],
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 1000, 'maxMessage' => 'Le message ne doit pas dépasser {{ limit }} caractères.']),
                ]
            ]);
    }
}
