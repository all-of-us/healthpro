<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Order;

class OrderModifyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $reasonType = $options['type'] . 'Reasons';
        $reasons = Order::$$reasonType;
        // Remove change tracking number option for non-kit orders
        if ($options['type'] === Order::ORDER_UNLOCK && $options['orderType'] !== 'kit') {
            if (($key = array_search('ORDER_AMEND_TRACKING', $reasons)) !== false) {
                unset($reasons[$key]);
            }
        }
        // Remove label error option for kit orders
        if ($options['type'] === Order::ORDER_CANCEL && $options['orderType'] === 'kit') {
            if (($key = array_search('ORDER_CANCEL_LABEL_ERROR', $reasons)) !== false) {
                unset($reasons[$key]);
            }
        }
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
        if ($options['type'] == Order::ORDER_CANCEL) {
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
            'orderType' => null,
            'type' => null
        ]);
    }
}
