<?php

namespace App\Form;

use App\Entity\Event;
use App\Entity\Photo;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class PhotoUploadFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('event', EntityType::class, [
                'class' => Event::class,
                'choice_label' => 'title',
                'label' => 'Événement associé',
            ])
            ->add('file', FileType::class, [
                'label' => 'Photo',
                'mapped' => false,
                'constraints' => [
                    new File(maxSize: '10M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'])
                ],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Téléverser']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Photo::class,
        ]);
    }
}
