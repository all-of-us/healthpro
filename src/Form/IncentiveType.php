<?php

namespace App\Form;

use App\Entity\Incentive;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class IncentiveType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $otherIncentiveAmount = $builder->getData() && !in_array(
            $builder->getData()->getIncentiveAmount(),
            Incentive::$incentiveAmountChoices
        ) ? $builder->getData()->getIncentiveAmount() : 0;

        $builder
            ->add('recipient', Type\ChoiceType::class, [
                'label' => 'Please select the recipient of the incentive*:',
                'choices' => Incentive::$recipientChoices,
                'placeholder' => '-- Select Recipient --',
                'multiple' => false,
                'required' => true,
                'constraints' => [
                    new Constraints\Callback(function ($value, $context) {
                        if (!$context->getRoot()['declined']->getData() && empty($value)) {
                            $context->buildViolation('Please specify recipient')->addViolation();
                        }
                    })
                ]
            ])
            ->add('incentive_date_given', Type\DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date of Service',
                'required' => true,
                'html5' => false,
                'format' => 'MM/dd/yyyy',
                'constraints' => [
                    new Constraints\Type('datetime'),
                    new Constraints\LessThanOrEqual([
                        'value' => new \DateTime('today'),
                        'message' => 'Date cannot be in the future'
                    ]),
                    new Constraints\Callback(function ($value, $context) {
                        if (!$context->getRoot()['declined']->getData() && empty($value)) {
                            $context->buildViolation('Please specify date of service')->addViolation();
                        }
                    })
                ],
                'attr' => [
                    'autocomplete' => 'off',
                    'class' => 'incentive-date-given toggle-required'
                ]
            ])
            ->add('incentive_type', Type\ChoiceType::class, [
                'label' => 'Incentive Type',
                'choices' => Incentive::getIncentiveOptions($options['pediatric_participant']),
                'placeholder' => '-- Select incentive type --',
                'multiple' => false,
                'required' => false,
                'constraints' => [
                    new Constraints\Callback(function ($value, $context) {
                        if (!$context->getRoot()['declined']->getData() && empty($value)) {
                            $context->buildViolation('Please specify incentive type')->addViolation();
                        }
                    })
                ],
                'attr' => [
                    'class' => 'toggle-required'
                ]
            ])
            ->add('gift_card_type', Type\TextType::class, [
                'label' => 'Specify Type of Gift Card',
                'required' => false,
                'constraints' => [
                    new Constraints\Type('string'),
                    new Constraints\Callback(function ($value, $context) {
                        if (!$context->getRoot()['declined']->getData() && $context->getRoot()['incentive_type']->getData() === 'gift_card' && empty($value)) {
                            $context->buildViolation('Please specify type of gift card')->addViolation();
                        }
                    })
                ],
                'attr' => [
                    'class' => 'gift-card',
                    'autocomplete' => 'off'
                ]
            ])
            ->add('type_of_item', Type\TextType::class, [
                'label' => 'Specify Type of Item',
                'required' => false,
                'constraints' => [
                    new Constraints\Type('string'),
                    new Constraints\Callback(function ($value, $context) {
                        if (!$context->getRoot()['declined']->getData() && $context->getRoot()['incentive_type']->getData() === Incentive::ITEM_OF_APPRECIATION && empty($value)) {
                            $context->buildViolation('Please specify type of item')->addViolation();
                        }
                    })
                ],
                'attr' => [
                    'class' => 'item-type',
                    'autocomplete' => 'off',
                ]
            ])
            ->add('number_of_items', Type\IntegerType::class, [
                'label' => 'Number of Items',
                'required' => false,
                'constraints' => [
                    new Constraints\Type('integer'),
                    new Constraints\Callback(function ($value, $context) {
                        if (!$context->getRoot()['declined']->getData() && $context->getRoot()['incentive_type']->getData() === Incentive::ITEM_OF_APPRECIATION && empty($value)) {
                            $context->buildViolation('Please specify number of items')->addViolation();
                        }
                    }),
                    new Constraints\Range(['min' => 1, 'max' => 1000])
                ],
                'attr' => [
                    'class' => 'item-type',
                    'autocomplete' => 'off',
                ]
            ])
            ->add('other_incentive_type', Type\TextType::class, [
                'label' => 'Specify Other',
                'required' => false,
                'constraints' => [
                    new Constraints\Type('string'),
                    new Constraints\Callback(function ($value, $context) {
                        if (!$context->getRoot()['declined']->getData() && $context->getRoot()['incentive_type']->getData() === 'other' && empty($value)) {
                            $context->buildViolation('Please specify other incentive type')->addViolation();
                        }
                    })
                ]
            ])
            ->add('incentive_occurrence', Type\ChoiceType::class, [
                'label' => 'Incentive Occurrence',
                'choices' => Incentive::getIncentiveOccurenceOptions($options['pediatric_participant']),
                'placeholder' => '-- Select incentive occurrence --',
                'multiple' => false,
                'required' => false,
                'constraints' => [
                    new Constraints\Callback(function ($value, $context) {
                        if (!$context->getRoot()['declined']->getData() && empty($value)) {
                            $context->buildViolation('Please specify incentive occurrence')->addViolation();
                        }
                    })
                ],
                'attr' => [
                    'class' => 'toggle-required'
                ]
            ])
            ->add('other_incentive_occurrence', Type\TextType::class, [
                'label' => 'Specify Other',
                'required' => false,
                'constraints' => [
                    new Constraints\Type('string'),
                    new Constraints\Callback(function ($value, $context) {
                        if (!$context->getRoot()['declined']->getData() && $context->getRoot()['incentive_occurrence']->getData() === 'other' && empty($value)) {
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
                'required' => false,
                'attr' => [
                    'class' => 'toggle-required'
                ],
                'constraints' => [
                    new Constraints\Callback(function ($value, $context) {
                        if (!$context->getRoot()['declined']->getData() &&
                            ($context->getRoot()['incentive_type']->getData() !== 'promotional' && $context->getRoot()['incentive_type']->getData() !== Incentive::ITEM_OF_APPRECIATION) &&
                            empty($value)) {
                            $context->buildViolation('Please specify incentive amount')->addViolation();
                        }
                    })
                ],
                'getter' => function (Incentive $incentive) {
                    if ($incentive->getIncentiveAmount() && !in_array($incentive->getIncentiveAmount(), Incentive::$incentiveAmountChoices)) {
                        return 'other';
                    }
                    return $incentive->getIncentiveAmount();
                }
            ])
            ->add('other_incentive_amount', Type\IntegerType::class, [
                'label' => 'Specify Other',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new Constraints\Type('integer'),
                    new Constraints\Callback(function ($value, $context) {
                        if (!$context->getRoot()['declined']->getData() && $context->getRoot()['incentive_amount']->getData() === 'other' && empty($value)) {
                            $context->buildViolation('Please specify other incentive amount')->addViolation();
                        }
                    })
                ],
                'attr' => [
                    'autocomplete' => 'off',
                    'min' => 1
                ],
                'data' => $otherIncentiveAmount
            ])
            ->add('notes', Type\TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'constraints' => [
                    new Constraints\Type('string'),
                    new Constraints\Length(['max' => 285]),
                    new Constraints\Callback(function ($value, $context) use ($options) {
                        if ($options['require_notes'] && !$context->getRoot()['declined']->getData() && empty($value)) {
                            $context->buildViolation('Please specify notes')->addViolation();
                        }
                    })
                ],
                'attr' => [
                    'data-parsley-maxlength' => 280,
                    'class' => $options['require_notes'] ? 'toggle-required' : ''
                ]
            ])
            ->add('declined', Type\CheckboxType::class, [
                'label' => 'Participant declined incentive',
                'required' => false,
                'attr' => [
                    'class' => 'incentive-declined'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Incentive::class,
            'require_notes' => false,
            'pediatric_participant' => false
        ]);
    }
}
