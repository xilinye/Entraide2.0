<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\{ChoiceType, TextareaType};
use Symfony\Component\Form\CallbackTransformer;

class RatingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('score', ChoiceType::class, [
                'choices' => [
                    '1' => 1,
                    '2' => 2,
                    '3' => 3,
                    '4' => 4,
                    '5' => 5
                ],
                'expanded' => true,
                'multiple' => false,
                'label' => false,
                'choice_label' => false,
                'attr' => ['class' => 'star-rating'],
                'empty_data' => '0',
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Commentaire (optionnel)',
                'required' => false,
                'attr' => ['rows' => 3]
            ]);
        $builder->get('score')
            ->addModelTransformer(new CallbackTransformer(
                function ($scoreAsInt) {
                    return $scoreAsInt !== null ? (string)$scoreAsInt : null;
                },
                function ($scoreAsString) {
                    return $scoreAsString !== null ? (int)$scoreAsString : null;
                }
            ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'App\Entity\Rating',
        ]);
    }
}
