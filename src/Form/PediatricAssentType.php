<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class PediatricAssentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pediatricAssent', Type\ChoiceType::class, [
                'label' => $options['assentQuestion'],
                'required' => false,
                'placeholder' => 'Select',
                'choices' => [
                    'Yes' => 'yes',
                    'No' => 'no',
                    'Participant is unable to provide assent' => 'unable',
                ],
                'constraints' => [
                    new Constraints\NotBlank([
                        'message' => 'Please select an assent response.',
                    ]),
                    new Constraints\Choice([
                        'choices' => ['yes', 'no', 'unable'],
                        'message' => 'Please select an assent response.',
                    ]),
                ],
            ])
            ->add('acknowledgeNoAssent', Type\HiddenType::class, [
                'required' => false,
                'empty_data' => '0',
            ])
            ->add('pediatricAssentId', Type\HiddenType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'assentQuestion' => '',
        ]);
        $resolver->setAllowedTypes('assentQuestion', 'string');
    }
}
