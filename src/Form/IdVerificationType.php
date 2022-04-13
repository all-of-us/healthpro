<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IdVerificationType extends AbstractType
{
    public static $verificationTypeChoices = [
        'Photo ID and one form of PII' => 'PHOTO_AND_ONE_OF_PII',
        'Two forms of PII' => 'TWO_OF_PII'
    ];

    public static $visitTypeChoices = [
        'PM&B initial visit' => 'PMB_INITIAL_VISIT',
        'Physical measurements only' => 'PHYSICAL_MEASUREMENTS_ONLY',
        'Biospecimen collection only' => 'BIOSPECIMEN_COLLECTION_ONLY',
        'Biospecimen redraw' => 'BIOSPECIMEN_COLLECTION_ONLY',
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
