<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'Prénom'])
            ->add('lastName', TextType::class, ['label' => 'Nom'])
            ->add('age', IntegerType::class, [
                'label' => 'Âge',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: "L'âge est requis (minimum 18 ans)."),
                    new Assert\GreaterThanOrEqual(value: 18, message: "Vous devez avoir au moins 18 ans pour utiliser le site."),
                ],
            ])
            ->add('email', EmailType::class, ['label' => 'Email', 'disabled' => true])
            ->add('postalCode', TextType::class, ['label' => 'Code postal', 'required' => false])
            ->add('cityOrRegion', TextType::class, ['label' => 'Ville ou région', 'required' => false])
            ->add('submit', SubmitType::class, ['label' => 'Enregistrer']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
