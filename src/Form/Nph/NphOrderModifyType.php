<?php

namespace App\Form\Nph;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\NphOrder;

class NphOrderModifyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $reasonType = $options['type'] . 'Reasons';
        $reasons = NphOrder::$$reasonType;

        $builder->add('reason', Type\ChoiceType::class, [
            'label' => 'Reason',
            'required' => true,
            'choices' => $reasons,
            'placeholder' => '-- Select ' . ucfirst($options['type']) . ' Reason --',
            'multiple' => false,
            'constraints' => new Constraints\NotBlank([
                'message' => "Please select {$options['type']} reason"
            ]),
            'attr' => ['class' => 'modify-reason']
        ]);
        $builder->add('otherText', Type\TextareaType::class, [
            'label' => false,
            'required' => false,
            'constraints' => [
                new Constraints\Type('string'),
                new Constraints\Callback(function ($value, $context) {
                    if (empty($value) && $context->getRoot()['reason']->getData() === 'OTHER') {
                        $context->buildViolation('Please enter a reason')->addViolation();
                    }
                })
            ],
            'attr' => ['class' => 'modify-other-text']
        ]);
        if ($options['type'] == NphOrder::ORDER_CANCEL) {
            $builder->add('confirm', Type\TextType::class, [
                'label' => 'Confirm',
                'required' => true,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string'),
                    new Constraints\Callback(function ($value, $context) {
                        if (strtolower($value) !== NphOrder::ORDER_CANCEL) {
                            $context->buildViolation('Please type the word "CANCEL" to confirm')->addViolation();
                        }
                    })
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
