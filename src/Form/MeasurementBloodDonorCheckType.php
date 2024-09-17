<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints;

class MeasurementBloodDonorCheckType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('bloodDonor', Type\ChoiceType::class, [
                'expanded' => true,
                'multiple' => false,
                'required' => true,
                'label' => 'Is the participant on site for a blood donation or apheresis?',
                'choices' => [
                    'Yes' => 'yes',
                    'No' => 'no',
                ],
                'constraints' => new Constraints\NotBlank([
                    'message' => 'Please select an option'
                ])
            ])
            ->add('bloodDonorType', Type\ChoiceType::class, [
                'expanded' => true,
                'multiple' => false,
                'required' => true,
                'label' => 'Select the type of blood donation',
                'choices' => [
                    'Apheresis' => 'apheresis',
                    'Whole Blood' => 'whole-blood',
                ],
                'constraints' => new Constraints\Callback(function ($value, $context) {
                    if ($context->getRoot()['bloodDonor']->getData() !== 'no' && empty($value)) {
                        $context->buildViolation('Please select an option')->addViolation();
                    }
                })
            ]);
    }
}
