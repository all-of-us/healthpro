<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints;

class MeasurementPediatricAssentCheckType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('assent', Type\ChoiceType::class, [
                'expanded' => false,
                'multiple' => false,
                'required' => true,
                'label' => 'Does the pediatric participant assent to physical measurements during this visit?',
                'choices' => [
                    'Select' => '',
                    'Yes' => 'yes',
                    'No' => 'no',
                ],
                'constraints' => new Constraints\Callback(function ($value, $context) {
                    if (empty($value)) {
                        $context->buildViolation('Please select an option')->addViolation();
                    }
                })
            ]);
    }
}
