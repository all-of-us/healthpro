<?php

namespace App\Form;

use App\Entity\Incentive;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IncentiveType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('incentive_date_given', Type\DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date Incentive Given',
                'required' => true,
                'html5' => false,
                'format' => 'MM/dd/yyyy',
                'constraints' => [
                    new Constraints\Type('datetime'),
                    new Constraints\LessThanOrEqual([
                        'value' => new \DateTime('today'),
                        'message' => 'Date cannot be in the future'
                    ])
                ]
            ])
            ->add('incentive_type', Type\ChoiceType::class, [
                'label' => 'Incentive Type',
                'choices' => [
                    'Cash' => 'cash',
                    'Gift Card' => 'gift_card',
                    'Voucher' => 'voucher',
                    'Promotional Item' => 'promotional',
                    'Other' => 'other'
                ],
                'placeholder' => '-- Select incentive type --',
                'multiple' => false,
                'required' => true
            ])
            ->add('gift_card_type', Type\TextType::class, [
                'label' => 'Specify Type of Gift Card',
                'required' => false,
                'constraints' => new Constraints\Type('string')
            ])
            ->add('other_incentive_type', Type\TextType::class, [
                'label' => 'Specify Other',
                'required' => false,
                'constraints' => new Constraints\Type('string')
            ])
            ->add('incentive_occurrence', Type\ChoiceType::class, [
                'label' => 'Incentive Occurrence',
                'choices' => [
                    'One-time Incentive' => 'one_time',
                    'Redraw' => 'redraw',
                    'Other' => 'other'
                ],
                'placeholder' => '-- Select incentive occurrence --',
                'multiple' => false,
                'required' => true
            ])
            ->add('other_incentive_occurrence', Type\TextType::class, [
                'label' => 'Specify Other',
                'required' => false,
                'constraints' => new Constraints\Type('string')
            ])
            ->add('incentive_amount', Type\ChoiceType::class, [
                'label' => 'Incentive Amount',
                'choices' => [
                    '$25.00' => '25',
                    '$15.00' => '15',
                    'Other' => 'other'
                ],
                'placeholder' => '-- Select amount --',
                'multiple' => false,
                'required' => true
            ])
            ->add('other_incentive_amount', Type\TextType::class, [
                'label' => 'Specify Other',
                'required' => false,
                'constraints' => new Constraints\Type('string')
            ])
            ->add('notes', Type\TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'constraints' => new Constraints\Type('string')
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Incentive::class
        ]);
    }
}
