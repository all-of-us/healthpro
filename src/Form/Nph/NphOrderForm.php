<?php

namespace App\Form\Nph;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints;

class NphOrderForm extends AbstractType
{
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
        'I was constipated (had difficulty passing stool), and my stool looks like Type 1 and/or 2' => 'difficult',
        'I had diarrhea (watery stool), and my stool looks like Type 5, 6, and/or 7' => 'watery',
        'I had normal formed stool, and my stool looks like Type 3 and/or 4' => 'normal'
    ];

    public static $bowelMovementQuality = [
        'I tend to be constipated (have difficulty passing stool) - Type 1 and 2' => 'difficult',
        'I tend to have diarrhea (watery stool) - Type 5, 6, and 7' => 'watery',
        'I tend to have normal formed stool - Type 3 and 4' => 'normal'
    ];

    protected function addCollectedTimeAndNoteFields(
        FormBuilderInterface $builder,
        array $options,
        string $sample,
        string $formType = 'finalize'
    ): void {
        $constraintDateTime = new \DateTime('+5 minutes'); // add buffer for time skew
        $constraints = [
            new Constraints\Type('datetime'),
            new Constraints\LessThanOrEqual([
                'value' => $constraintDateTime,
                'message' => 'Date cannot be in the future'
            ])
        ];
        if ($formType === 'collect') {
            $constraints[] = new Constraints\Callback(function ($value, $context) use ($sample) {
                if (empty($value) && $context->getRoot()[$sample]->getData() === true) {
                    $context->buildViolation('Collection time required')->addViolation();
                }
            });
        }
        $builder->add("{$sample}CollectedTs", Type\DateTimeType::class, [
            'required' => $formType === 'finalize',
            'label' => 'Collection Time',
            'widget' => 'single_text',
            'format' => 'M/d/yyyy h:mm a',
            'html5' => false,
            'model_timezone' => 'UTC',
            'view_timezone' => $options['timeZone'],
            'constraints' => $constraints,
            'attr' => [
                'class' => 'order-ts',
            ]
        ]);
        $builder->add("{$sample}Notes", Type\TextareaType::class, [
            'label' => 'Notes',
            'required' => false,
            'constraints' => new Constraints\Type('string')
        ]);
    }

    protected function addUrineMetadataFields(FormBuilderInterface $builder): void
    {
        $builder->add('urineColor', Type\ChoiceType::class, [
            'label' => 'Urine Color',
            'required' => true,
            'choices' => NphOrderCollect::$urineColors,
            'multiple' => false,
            'placeholder' => 'Select Urine Color'
        ]);

        $builder->add('urineClarity', Type\ChoiceType::class, [
            'label' => 'Urine Clarity',
            'required' => true,
            'choices' => NphOrderCollect::$urineClarity,
            'multiple' => false,
            'placeholder' => 'Select Urine Clarity'
        ]);
    }

    protected function addStoolMetadataFields(FormBuilderInterface $builder): void
    {
        $builder->add('bowelType', Type\ChoiceType::class, [
            'label' => 'Describe the bowel movement for this collection',
            'required' => true,
            'choices' => self::$bowelMovements,
            'multiple' => false,
            'placeholder' => 'Select bowel movement type'
        ]);

        $builder->add('bowelQuality', Type\ChoiceType::class, [
            'label' => 'Describe the typical quality of your bowel movements',
            'required' => true,
            'choices' => self::$bowelMovementQuality,
            'multiple' => false,
            'placeholder' => 'Select bowel movement quality'
        ]);
    }
}
