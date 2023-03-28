<?php

namespace App\Form;

use App\Entity\Measurement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class MeasurementModifyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $reasonType = $options['type'] . 'Reasons';
        $reasons = Measurement::$$reasonType;
        $builder->add('reason', Type\ChoiceType::class, [
            'label' => 'Reason',
            'required' => true,
            'choices' => $reasons,
            'placeholder' => '-- Select ' . ucfirst($options['type']) . ' Reason --',
            'multiple' => false,
            'constraints' => new Constraints\NotBlank([
                'message' => "Please select {$options['type']} reason"
            ])
        ]);
        $builder->add('other_text', Type\TextareaType::class, [
            'label' => false,
            'required' => false,
            'constraints' => [
                new Constraints\Type('string')
            ]
        ]);
        if ($options['type'] == Measurement::EVALUATION_CANCEL) {
            $builder->add('confirm', Type\TextType::class, [
                'label' => 'Confirm',
                'required' => true,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => 'Type the word "CANCEL" to confirm',
                    'autocomplete' => 'off'
                ]
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'type' => null
        ]);
    }
}
