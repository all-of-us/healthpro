<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class RemoveGroupMemberType extends AbstractType
{
    public const ATTESTATIONS = [
        'I attest that this user has left the program <strong class="attest-choice-text">voluntarily.</strong>' => 'yes',
        'I attest that this user has left the program <strong class="attest-choice-text">involuntarily.</strong>' => 'no'
    ];

    public const EMAIL_ATTESTATIONS = [
        'I attest that this user has left the program voluntarily' => 'yes',
        'I attest that this user has left the program involuntarily' => 'no'
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('confirm', Type\ChoiceType::class, [
                'label' => 'Are you sure you want to remove this member?',
                'required' => true,
                'expanded' => true,
                'multiple' => false,
                'choices' => [
                    'Yes' => 'yes',
                    'No' => 'no'
                ],
                'constraints' => [
                    new Constraints\NotBlank()
                ]
            ])
            ->add('memberLastDay', Type\DateType::class, [
                'widget' => 'single_text',
                'label' => "Please select the member's last day",
                'required' => false,
                'html5' => false,
                'format' => 'M/d/yyyy',
                'attr' => ['autocomplete' => 'off'],
                'constraints' => [
                    new Constraints\Type('datetime'),
                    new Constraints\LessThanOrEqual([
                        'value' => new \DateTime('today'),
                        'message' => 'Date cannot be in the future'
                    ]),
                    new Constraints\Callback(function ($memberLastDate, $context) {
                        $confirmRemove = $context->getObject()->getParent()->get('confirm')->getData();
                        $removeReason = $context->getObject()->getParent()->get('reason')->getData();
                        if ($confirmRemove === 'yes' && $removeReason === 'no' && empty($memberLastDate)) {
                            $context->buildViolation('Please enter member last date')->addViolation();
                        }
                    })
                ]
            ])
            ->add('reason', Type\ChoiceType::class, [
                'label' => 'Please provide the reason for removal',
                'required' => false,
                'expanded' => true,
                'multiple' => false,
                'placeholder' => false,
                'choices' => [
                    "Staff member no longer supports the {$options['programDisplayText']} program or has left the institution" => 'no',
                    "Staff member still supports the {$options['programDisplayText']} program but not this specific site" => 'yes'
                ],
                'constraints' => [
                    new Constraints\Callback(function ($removeReason, $context) {
                        $confirmRemove = $context->getObject()->getParent()->get('confirm')->getData();
                        if ($confirmRemove === 'yes' && empty($removeReason)) {
                            $context->buildViolation('Please select reason')->addViolation();
                        }
                    })
                ]
            ])
            ->add('attestation', Type\ChoiceType::class, [
                'label' => 'Please select one',
                'label_html' => true,
                'required' => false,
                'expanded' => true,
                'multiple' => false,
                'placeholder' => false,
                'choices' => self::ATTESTATIONS,
                'constraints' => [
                    new Constraints\Callback(function ($attestation, $context) {
                        $confirmRemove = $context->getObject()->getParent()->get('confirm')->getData();
                        $reason = $context->getObject()->getParent()->get('reason')->getData();
                        if ($confirmRemove === 'yes' && $reason === 'no' && empty($attestation)) {
                            $context->buildViolation('Please select one')->addViolation();
                        }
                    })
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'programDisplayText' => null
        ]);
    }
}
