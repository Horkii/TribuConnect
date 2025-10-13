<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;

class PhotoFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('image', FileType::class, [
                'label' => 'Ajouter une photo',
                'mapped' => false,
                'required' => true,
                'constraints' => [new Image(maxSize: '10M')],
            ])
            ->add('caption', TextareaType::class, [
                'label' => 'Commentaire (optionnel)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'maxlength' => 200,
                    'placeholder' => 'Commentaire (200 caractères max)'
                ],
                'constraints' => [new Length(max: 200)],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Téléverser']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
