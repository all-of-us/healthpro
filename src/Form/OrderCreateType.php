<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class OrderCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['orderType'] === 'dv') {
            $builder
                ->add('kitId', Type\RepeatedType::class, [
                    'type' => Type\TextType::class,
                    'invalid_message' => 'The kit order ID fields must match.',
                    'first_options' => [
                        'label' => 'Kit order ID'
                    ],
                    'second_options' => [
                        'label' => 'Verify kit order ID',
                    ],
                    'options' => [
                        'attr' => ['placeholder' => 'Scan barcode']
                    ],
                    'required' => false,
                    'error_mapping' => [
                        '.' => 'second' // target the second (repeated) field for non-matching error
                    ],
                    'constraints' => [
                        new Constraints\Regex([
                            'pattern' => '/^KIT-\d{8}$/',
                            'message' => 'Must be in the format of KIT-12345678 ("KIT-" followed by 8 digits)'
                        ])
                    ]
                ]);
        } else {
            $showBloodTubes = $options['showBloodTubes'];
            $nonBloodSamples = $options['nonBloodSamples'];
            $builder
                ->add('samples', Type\ChoiceType::class, [
                    'expanded' => true,
                    'multiple' => true,
                    'label' => 'Select requested samples',
                    'choices' => $options['samples'],
                    'required' => false,
                    'choice_attr' => function ($val) use ($showBloodTubes, $nonBloodSamples) {
                        if ($showBloodTubes) {
                            return [];
                        }
                        return !in_array($val, $nonBloodSamples) ? ['disabled' => 'disabled'] : ['checked' => 'checked'];
                    }
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'orderType' => null,
            'samples' => null,
            'showBloodTubes' => null,
            'nonBloodSamples' => null
        ]);
    }
}
