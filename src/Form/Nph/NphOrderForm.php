<?php

namespace App\Form\Nph;

use App\Entity\NphOrder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints;

class NphOrderForm extends AbstractType
{
    public const FORM_FINALIZE_TYPE = 'finalize';
    public const FORM_COLLECT_TYPE = 'collect';

    public static $urineColors = [
        'Color 1' => 1,
        'Color 2' => 2,
        'Color 3' => 3,
        'Color 4' => 4,
        'Color 5' => 5,
        'Color 6' => 6,
        'Color 7' => 7,
        'Color 8' => 8,
    ];

    public static $urineClarity = [
        'Clean' => 'clean',
        'Slightly Cloudy' => 'slightly_cloudy',
        'Cloudy' => 'cloudy',
        'Turbid' => 'turbid'
    ];

    public static $bowelMovements = [
        'Response not provided' => 'not_provided',
        'I was constipated (had difficulty passing stool), and my stool looks like Type 1 and/or 2' => 'difficult',
        'I had diarrhea (watery stool), and my stool looks like Type 5, 6, and/or 7' => 'watery',
        'I had normal formed stool, and my stool looks like Type 3 and/or 4' => 'normal'
    ];

    public static $bowelMovementQuality = [
        'Response not provided' => 'not_provided',
        'I tend to be constipated (have difficulty passing stool) - Type 1 and 2' => 'difficult',
        'I tend to have diarrhea (watery stool) - Type 5, 6, and 7' => 'watery',
        'I tend to have normal formed stool - Type 3 and 4' => 'normal'
    ];

    protected function addCollectedTimeAndNoteFields(
        FormBuilderInterface $builder,
        array $options,
        string $sample,
        bool $disabled = false,
        string $formType = self::FORM_FINALIZE_TYPE
    ): void {
        if ($formType === self::FORM_FINALIZE_TYPE || $options['orderType'] !== NphOrder::TYPE_STOOL) {
            $constraints = $this->getDateTimeConstraints();
            if ($formType === self::FORM_COLLECT_TYPE) {
                $constraints[] = new Constraints\Callback(function ($value, $context) use ($sample) {
                    if (empty($value) && $context->getRoot()[$sample]->getData() === true) {
                        $context->buildViolation('Collection time is required')->addViolation();
                    }
                });
            } else {
                $constraints[] = new Constraints\NotBlank([
                    'message' => 'Collection time is required'
                ]);
            }
            $constraints[] = $this->getCollectedTimeGreaterThanConstraint($options['orderCreatedTs']);
            $orderCreatedTs = clone $options['orderCreatedTs'];
            $userTimeZone = new \DateTimeZone($options['timeZone']);
            $orderCreatedTs->setTimezone($userTimeZone);
            $builder->add("{$sample}CollectedTs", Type\DateTimeType::class, [
                'required' => false,
                'label' => 'Collection Time',
                'widget' => 'single_text',
                'format' => 'M/d/yyyy h:mm a',
                'html5' => false,
                'model_timezone' => 'UTC',
                'view_timezone' => $options['timeZone'],
                'constraints' => $constraints,
                'attr' => [
                    'class' => 'order-ts',
                    'readonly' => $options['disableStoolCollectedTs'],
                    'data-parsley-custom-date-comparison' => $orderCreatedTs->format('m/d/Y g:i A')
                ],
                'disabled' => $disabled
            ]);
        }
        $builder->add("{$sample}Notes", Type\TextareaType::class, [
            'label' => 'Notes',
            'required' => false,
            'constraints' => new Constraints\Type('string'),
            'disabled' => $disabled
        ]);
    }

    protected function addUrineMetadataFields(
        FormBuilderInterface $builder,
        bool $disabled = false,
        string $formType = self::FORM_FINALIZE_TYPE
    ): void {
        $urineColorOptions = [
            'label' => 'Urine Color',
            'required' => false,
            'choices' => NphOrderCollect::$urineColors,
            'multiple' => false,
            'placeholder' => 'Select Urine Color',
            'disabled' => $disabled
        ];
        $urineClarityOptions = [
            'label' => 'Urine Clarity',
            'required' => false,
            'choices' => NphOrderCollect::$urineClarity,
            'multiple' => false,
            'placeholder' => 'Select Urine Clarity',
            'disabled' => $disabled
        ];
        if ($formType === self::FORM_FINALIZE_TYPE) {
            $urineColorOptions['constraints'] = $this->getNotBlankConstraint();
            $urineClarityOptions['constraints'] = $this->getNotBlankConstraint();
        }
        $builder->add('urineColor', Type\ChoiceType::class, $urineColorOptions);
        $builder->add('urineClarity', Type\ChoiceType::class, $urineClarityOptions);
    }

    protected function addStoolMetadataFields(
        FormBuilderInterface $builder,
        string $timeZone,
        string $sample,
        bool $disabled = false,
        bool $disableFreezeTs = false,
        string $formType = self::FORM_FINALIZE_TYPE,
    ): void {
        $required = $formType === self::FORM_FINALIZE_TYPE;
        $disableFreezeTsField = $disabled || $disableFreezeTs;
        $bowelTypeOptions = [
            'label' => 'Describe the bowel movement for this collection',
            'required' => $required,
            'choices' => self::$bowelMovements,
            'multiple' => false,
            'placeholder' => 'Select bowel movement type',
            'disabled' => $disabled
        ];
        $bowelQualityOptions = [
            'label' => 'Describe the typical quality of your bowel movements',
            'required' => $required,
            'choices' => self::$bowelMovementQuality,
            'multiple' => false,
            'placeholder' => 'Select bowel movement quality',
            'disabled' => $disabled
        ];
        if ($formType === self::FORM_FINALIZE_TYPE) {
            $bowelTypeOptions['constraints'] = $this->getNotBlankConstraint();
            $bowelQualityOptions['constraints'] = $this->getNotBlankConstraint();
        }
        $builder->add('bowelType', Type\ChoiceType::class, $bowelTypeOptions);
        $builder->add('bowelQuality', Type\ChoiceType::class, $bowelQualityOptions);
        if ($formType === self::FORM_FINALIZE_TYPE) {
            $builder->add('freezedTs', Type\DateTimeType::class, [
                'required' => !$disableFreezeTs,
                'label' => 'Freeze Time',
                'widget' => 'single_text',
                'format' => 'M/d/yyyy h:mm a',
                'html5' => false,
                'model_timezone' => 'UTC',
                'view_timezone' => $timeZone,
                'constraints' => [
                    new Constraints\LessThanOrEqual([
                        'value' => new \DateTime('now'),
                        'message' => 'Timestamp cannot be in the future'
                    ]),
                    new Constraints\Callback(function ($value, $context) use ($sample) {
                        $formData = $context->getRoot()->getData();
                        if (!empty($formData["{$sample}CollectedTs"]) && !empty($value)) {
                            if ($value <= $formData["{$sample}CollectedTs"]) {
                                $context->buildViolation('Freeze time must be after collection time')->addViolation();
                            }
                        }
                    })
                ],
                'attr' => [
                    'class' => 'order-ts freeze-ts',
                    'data-field-type' => 'freeze',
                    'data-parsley-freeze-date-comparison' => "nph_sample_finalize_{$sample}CollectedTs",
                    'data-parsley-required-message' => 'Freeze time is required'
                ],
                'disabled' => $disableFreezeTsField
            ]);
        }
    }

    protected function addUrineTotalCollectionVolume(
        FormBuilderInterface $builder,
        bool $disabled = false
    ): void {
        $urineVolumeCollection = [
            'label' => 'Total Collection Volume',
            'required' => false,
            'disabled' => $disabled,
            'constraints' => [
                new Constraints\Callback(function ($value, $context) {
                    if ($value === 0.0) {
                        $context->buildViolation('Total collection volume must be greater than 0')->addViolation();
                    } elseif (empty($value)) {
                        $context->buildViolation('Total collection volume is required')->addViolation();
                    }
                })
            ],
            'attr' => [
                'class' => 'total-collection-volume',
                'data-warning-min-volume' => 0.1,
                'data-warning-max-volume' => 10,
            ]
        ];
        $builder->add('totalCollectionVolume', Type\NumberType::class, $urineVolumeCollection);
    }

    protected function getDateTimeConstraints(): array
    {
        return [
            new Constraints\Type('datetime'),
            new Constraints\LessThanOrEqual([
                'value' => new \DateTime('now'),
                'message' => 'Time cannot be in the future'
            ])
        ];
    }

    protected function getCollectedTimeGreaterThanConstraint(\DateTime $dateTime): Constraints\GreaterThan
    {
        return new Constraints\GreaterThan([
            'value' => $dateTime,
            'message' => 'Time must be after order generation'
        ]);
    }

    private function getNotBlankConstraint(): Constraints\NotBlank
    {
        return new Constraints\NotBlank([
            'message' => 'Please select an option'
        ]);
    }
}
