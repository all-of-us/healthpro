<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IdVerificationType extends AbstractType
{
    public static $idVerificationChoices = [
        'verificationType' => [
            'A photo and at least one piece of PII' => 'PHOTO_AND_ONE_OF_PII',
            'At least two separate pieces of PII' => 'TWO_OF_PII'
        ],
        'visitType' => [
            'PM&B Initial Visit' => 'PMB_INITIAL_VISIT',
            'Physical Measurements Only' => 'PHYSICAL_MEASUREMENTS_ONLY',
            'Biospecimen Collection Only' => 'BIOSPECIMEN_COLLECTION_ONLY',
            'Biospecimen Redraw' => 'BIOSPECIMEN_REDRAW_ONLY',
            'Retention Activities' => 'RETENTION_ACTIVITIES',
            'Pediatric Visit' => 'PEDIATRIC_VISIT',
        ]
    ];

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('confirmation', Type\ChoiceType::class, [
                'label' => 'Confirmation',
                'choices' => ['Please confirm the participant’s identity has been verified.' => true],
                'expanded' => true,
                'multiple' => true,
                'required' => true
            ])
            ->add('verification_type', Type\ChoiceType::class, [
                'label' => 'Verification Type',
                'choices' => self::$idVerificationChoices['verificationType'],
                'placeholder' => '-- Select Verification Type --',
                'multiple' => false,
                'required' => true
            ])
            ->add('visit_type', Type\ChoiceType::class, [
                'label' => 'Visit Type',
                'choices' => self::$idVerificationChoices['visitType'],
                'placeholder' => '-- Select Visit Type --',
                'multiple' => false,
                'required' => true
            ])
            ->add('guardian_verified', Type\ChoiceType::class, [
                'label' => false,
                'choices' => ['Participant\'s identity confirmed via the guardian.' => true],
                'expanded' => true,
                'multiple' => true,
                'required' => true,
                'attr' => ['hidden' => true]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => null
        ]);
    }
}
