<?php

namespace App\Form;

use App\Entity\Message;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class MessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['include_title']) {
            $builder->add('title', TextType::class, [
                'label' => 'Titre du message',
                'attr' => ['placeholder' => 'Entrez un titre clair']
            ]);
        }

        $builder->add('content', TextareaType::class, [
            'label' => 'Contenu',
            'attr' => ['rows' => 5]
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Message::class,
            'include_title' => true,
        ]);
    }
}
