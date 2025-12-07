<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [ 'label' => 'Prénom' ])
            ->add('lastName', TextType::class, [ 'label' => 'Nom' ])
            ->add('birthDate', BirthdayType::class, [
                'label' => 'Date de naissance',
                'required' => true,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('email', EmailType::class, [ 'label' => 'Email' ])
            ->add('postalCode', TextType::class, [ 'label' => 'Code postal', 'required' => false ])
            ->add('cityOrRegion', TextType::class, [ 'label' => 'Ville ou région', 'required' => false ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => true,
                'constraints' => [
                    new Assert\Length(min: 8),
                    new Assert\Regex(
                        pattern: '/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/',
                        message: 'Le mot de passe doit contenir au moins une majuscule, un chiffre et un caractère spécial.'
                    ),
                ],
            ])
            ->add('submit', SubmitType::class, [ 'label' => "S'inscrire" ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
