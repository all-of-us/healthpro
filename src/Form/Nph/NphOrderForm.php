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
        string $sampleId,
        bool $disabled = false,
        string $formType = self::FORM_FINALIZE_TYPE
    ): void {
        if ($formType === self::FORM_FINALIZE_TYPE || $options['orderType'] !== NphOrder::TYPE_STOOL) {
            $constraints = $this->getDateTimeConstraints();
            if ($formType === self::FORM_COLLECT_TYPE) {
                $constraints[] = new Constraints\Callback(function ($value, $context) use ($sample, $sampleId) {
                    if (empty($value) && $context->getRoot()[$sample . $sampleId]->getData() === true) {
                        $context->buildViolation('Collection time is required')->addViolation();
                    }
                });
            } else {
                $constraints[] = new Constraints\NotBlank([
                    'message' => 'Collection time is required'
                ]);
            }
            $constraints[] = $this->getCollectedTimeGreaterThanConstraint($options['orderCreatedTs']);
            $builder->add("{$sample}{$sampleId}CollectedTs", Type\DateTimeType::class, [
                'required' => $formType === self::FORM_FINALIZE_TYPE,
                'label' => 'Collection Time',
                'widget' => 'single_text',
                'format' => 'M/d/yyyy h:mm a',
                'html5' => false,
                'model_timezone' => 'UTC',
                'view_timezone' => $options['timeZone'],
                'constraints' => $constraints,
                'attr' => [
                    'class' => 'order-ts',
                    'readonly' => $options['disableStoolCollectedTs']
                ],
                'disabled' => $disabled
            ]);
        }
        $builder->add("{$sample}{$sampleId}Notes", Type\TextareaType::class, [
            'label' => 'Notes',
            'required' => false,
            'constraints' => new Constraints\Type('string'),
            'disabled' => $disabled
        ]);
    }

    protected function addUrineMetadataFields(FormBuilderInterface $builder, $disabled = false): void
    {
        $builder->add('urineColor', Type\ChoiceType::class, [
            'label' => 'Urine Color',
            'required' => true,
            'choices' => NphOrderCollect::$urineColors,
            'multiple' => false,
            'placeholder' => 'Select Urine Color',
            'disabled' => $disabled
        ]);

        $builder->add('urineClarity', Type\ChoiceType::class, [
            'label' => 'Urine Clarity',
            'required' => true,
            'choices' => NphOrderCollect::$urineClarity,
            'multiple' => false,
            'placeholder' => 'Select Urine Clarity',
            'disabled' => $disabled
        ]);
    }

    protected function addStoolMetadataFields(FormBuilderInterface $builder, $disabled = false): void
    {
        $builder->add('bowelType', Type\ChoiceType::class, [
            'label' => 'Describe the bowel movement for this collection',
            'required' => true,
            'choices' => self::$bowelMovements,
            'multiple' => false,
            'placeholder' => 'Select bowel movement type',
            'disabled' => $disabled
        ]);

        $builder->add('bowelQuality', Type\ChoiceType::class, [
            'label' => 'Describe the typical quality of your bowel movements',
            'required' => true,
            'choices' => self::$bowelMovementQuality,
            'multiple' => false,
            'placeholder' => 'Select bowel movement quality',
            'disabled' => $disabled
        ]);
    }

    protected function getDateTimeConstraints(): array
    {
        return [
            new Constraints\Type('datetime'),
            new Constraints\LessThanOrEqual([
                'value' => new \DateTime('+5 minutes'), // add buffer for time skew
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
}
