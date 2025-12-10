<?php

namespace App\Form;

use App\Entity\WorkPattern;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WorkPatternFormType extends AbstractType
{
    private function shiftChoices(): array
    {
        return [
            'Congé' => WorkPattern::SHIFT_HOLIDAY,
            'Repos' => WorkPattern::SHIFT_REST,
            'Matin' => WorkPattern::SHIFT_MORNING,
            'Après-midi' => WorkPattern::SHIFT_AFTERNOON,
            'Nuit' => WorkPattern::SHIFT_NIGHT,
            'Journée' => WorkPattern::SHIFT_DAY,
            'Télétravail' => WorkPattern::SHIFT_REMOTE,
            'Déplacement' => WorkPattern::SHIFT_TRAVEL,
        ];
    }



    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cycleLength', ChoiceType::class, [
                'label' => 'Cycle (jours)',
                'choices' => [7=>7,8=>8,9=>9,10=>10,11=>11,12=>12,13=>13,14=>14,15=>15,16=>16,17=>17,18=>18,19=>19,20=>20,21=>21],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Début du cycle',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
        ;
        // Toujours ajouter 21 champs Jour X (le front les masque dynamiquement)
        $choices = $this->shiftChoices();
        for ($i = 1; $i <= 21; $i++) {
            $builder->add('d'.$i, ChoiceType::class, [
                'label' => 'Jour '.$i,
                'mapped' => false,
                'choices' => $choices,
            ]);
        }

        $builder->add('submit', SubmitType::class, ['label' => 'Enregistrer le rythme']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WorkPattern::class,
        ]);
    }
}
