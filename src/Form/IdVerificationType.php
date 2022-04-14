<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IdVerificationType extends AbstractType
{
    public static $verificationTypeChoices = [
        'Photo ID & One Form of PII' => 'PHOTO_AND_ONE_OF_PII',
        'Two Forms of PII' => 'TWO_OF_PII'
    ];

    public static $visitTypeChoices = [
        'PM&B Initial Visit' => 'PMB_INITIAL_VISIT',
        'Physical Measurements Only' => 'PHYSICAL_MEASUREMENTS_ONLY',
        'Biospecimen Collection Only' => 'BIOSPECIMEN_COLLECTION_ONLY',
        'Biospecimen Redraw' => 'BIOSPECIMEN_COLLECTION_ONLY',
        'Retention Activities' => 'RETENTION_ACTIVITIES',
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
                'choices' => self::$verificationTypeChoices,
                'placeholder' => '-- Select Verification Type --',
                'multiple' => false,
                'required' => true
            ])
            ->add('visit_type', Type\ChoiceType::class, [
                'label' => 'Visit Type',
                'choices' => self::$visitTypeChoices,
                'placeholder' => '-- Select Visit Type --',
                'multiple' => false,
                'required' => true
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => null
        ]);
    }
}
