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
                ],
                'attr' => [
                    'autocomplete' => 'off'
                ]
            ])
            ->add('incentive_type', Type\ChoiceType::class, [
                'label' => 'Incentive Type',
                'choices' => Incentive::$incentiveTypeChoices,
                'placeholder' => '-- Select incentive type --',
                'multiple' => false,
                'required' => true
            ])
            ->add('gift_card_type', Type\TextType::class, [
                'label' => 'Specify Type of Gift Card',
                'required' => false,
                'constraints' => [
                    new Constraints\Type('string'),
                    new Constraints\Callback(function ($value, $context) {
                        if ($context->getRoot()['incentive_type']->getData() === 'gift_card' && empty($value)) {
                            $context->buildViolation('Please specify type of gift card')->addViolation();
                        }
                    })
                ]
            ])
            ->add('other_incentive_type', Type\TextType::class, [
                'label' => 'Specify Other',
                'required' => false,
                'constraints' => [
                    new Constraints\Type('string'),
                    new Constraints\Callback(function ($value, $context) {
                        if ($context->getRoot()['incentive_type']->getData() === 'other' && empty($value)) {
                            $context->buildViolation('Please specify other incentive type')->addViolation();
                        }
                    })
                ]
            ])
            ->add('incentive_occurrence', Type\ChoiceType::class, [
                'label' => 'Incentive Occurrence',
                'choices' => Incentive::$incentiveOccurrenceChoices,
                'placeholder' => '-- Select incentive occurrence --',
                'multiple' => false,
                'required' => true
            ])
            ->add('other_incentive_occurrence', Type\TextType::class, [
                'label' => 'Specify Other',
                'required' => false,
                'constraints' => [
                    new Constraints\Type('string'),
                    new Constraints\Callback(function ($value, $context) {
                        if ($context->getRoot()['incentive_occurrence']->getData() === 'other' && empty($value)) {
                            $context->buildViolation('Please specify other incentive occurrence')->addViolation();
                        }
                    })
                ]
            ])
            ->add('incentive_amount', Type\ChoiceType::class, [
                'label' => 'Incentive Amount',
                'choices' => Incentive::$incentiveAmountChoices,
                'placeholder' => '-- Select amount --',
                'multiple' => false,
                'required' => true
            ])
            ->add('other_incentive_amount', Type\IntegerType::class, [
                'label' => 'Specify Other',
                'required' => false,
                'constraints' => [
                    new Constraints\Type('integer'),
                    new Constraints\Callback(function ($value, $context) {
                        if ($context->getRoot()['incentive_amount']->getData() === 'other' && empty($value)) {
                            $context->buildViolation('Please specify other incentive amount')->addViolation();
                        }
                    })
                ],
                'attr' => [
                    'autocomplete' => 'off'
                ]
            ])
            ->add('notes', Type\TextareaType::class, [
                'label' => 'Notes',
                'required' => $options['require_notes'],
                'constraints' => new Constraints\Type('string'),
                'attr' => [
                    'data-parsley-error-message' => 'Please provide a reason for amending this incentive.'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Incentive::class,
            'require_notes' => false
        ]);
    }
}
