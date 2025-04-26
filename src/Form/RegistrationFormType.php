<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\{AbstractType, FormBuilderInterface};
use Symfony\Component\Validator\Constraints\{Regex, Length, NotBlank, Email};
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\{TextType, EmailType, PasswordType, RepeatedType, CheckboxType};
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pseudo', TextType::class, [
                'label' => 'Pseudonyme',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un pseudonyme',
                    ]),
                    new Length([
                        'min' => 3,
                        'minMessage' => 'Votre pseudonyme doit contenir au moins {{ limit }} caractères',
                        'max' => 50,
                        'maxMessage' => 'Votre pseudonyme ne peut pas dépasser {{ limit }} caractères'
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9_]+$/',
                        'message' => 'Seuls les lettres, chiffres et underscores sont autorisés'
                    ])
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'constraints' => [
                    new NotBlank(),
                    new Email()
                ]
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les mots de passe doivent correspondre.',
                'options' => ['attr' => ['class' => 'password-field']],
                'required' => true,
                'first_options'  => [
                    'label' => 'Mot de passe',
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Veuillez entrer un mot de passe',
                        ]),
                        new Length([
                            'min' => 8,
                            'minMessage' => 'Votre mot de passe doit contenir au moins {{ limit }} caractères',
                            'max' => 4096,
                        ]),
                        new Regex([
                            'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/',
                            'message' => 'Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial'
                        ])
                    ],
                ],
                'second_options' => ['label' => 'Confirmation'],
                'mapped' => false
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    new Assert\IsTrue([
                        'message' => 'Vous devez accepter les CGU et la politique de confidentialité.',
                    ]),
                ],
                'label' => false,
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'registration_form',
        ]);
    }
}
