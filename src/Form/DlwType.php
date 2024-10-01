<?php

namespace App\Form;

use App\Entity\NphDlw;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class DlwType extends AbstractType
{
    private const DOSE_BATCH_ID_DIGITS = 8;
    private const WEIGHT_DOSE_GREATER_THAN = 0;
    private const PARTICIPANT_WEIGHT_LESS_THAN_WEIGHT = 907;
    private const DOSE_BATCH_ID_REQUIRED_ERROR_MESSAGE = 'Dose batch ID required.';
    private const DOSE_BATCH_ID_INVALID_ERROR_MESSAGE = 'Dose Batch ID invalid, Please enter a valid dose batch ID (' . self::DOSE_BATCH_ID_DIGITS . ' digits).';
    private const ACTUAL_DOSE_REQUIRED_ERROR_MESSAGE = 'Actual dose required.';
    private const ACTUAL_DOSE_GREATER_THAN_ERROR_MESSAGE = 'Please verify the dose is correct. Value should be greater than ' . self::WEIGHT_DOSE_GREATER_THAN . '.';
    private const ACTUAL_DOSE_TENTH_PLACE_ERROR_MESSAGE = 'Please verify the dose is correct. Value can be entered up to the tenths (0.1) place.';
    private const PARTICIPANT_WEIGHT_REQUIRED_ERROR_MESSAGE = 'Participant weight required';
    private const PARTICIPANT_WEIGHT_GREATER_THAN_ERROR_MESSAGE = 'Please verify the measurement is correct. Value should be greater than ' . self::WEIGHT_DOSE_GREATER_THAN . ' kg.';
    private const PARTICIPANT_WEIGHT_LESS_THAN_ERROR_MESSAGE = 'Please verify the measurement is correct. Value should be less than ' . self::PARTICIPANT_WEIGHT_LESS_THAN_WEIGHT . ' kg.';
    private const PARTICIPANT_WEIGHT_TENTH_PLACE_ERROR_MESSAGE = 'Please verify the measurement is correct. Value can be entered up to the tenths (0.1) place.';
    private const DOSE_DATE_TIME_REQUIRED = 'Dose date/time required.';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $constraintDate = new \DateTime('today', new \DateTimeZone($options['timezone']));
        $builder
            ->add('doseBatchId', TextType::class, [
                'label' => 'Dose Batch ID',
                'constraints' => [
                    new NotBlank(),
                    new Length([
                        'min' => $this::DOSE_BATCH_ID_DIGITS,
                        'max' => $this::DOSE_BATCH_ID_DIGITS,
                        'exactMessage' => self::DOSE_BATCH_ID_INVALID_ERROR_MESSAGE
                    ])
                ],
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'data-parsley-required-message' => self::DOSE_BATCH_ID_REQUIRED_ERROR_MESSAGE,
                    'data-parsley-pattern-message' => self::DOSE_BATCH_ID_INVALID_ERROR_MESSAGE,
                    'data-parsley-pattern' => '^\d{' . self::DOSE_BATCH_ID_DIGITS . '}$'
                ],
            ])
            ->add('actualDose', NumberType::class, [
                'label' => 'Actual Dose (g)*',
                'required' => true,
                'empty_data' => 0,
                'constraints' => [
                    new NotBlank(),
                    new GreaterThan([
                        'value' => self::WEIGHT_DOSE_GREATER_THAN,
                        'message' => self::ACTUAL_DOSE_GREATER_THAN_ERROR_MESSAGE
                    ]),
                    new Callback(function ($value, $context) {
                        if ($this->getNumDecimalPlaces($value) > 1) {
                            $context->buildViolation(self::ACTUAL_DOSE_TENTH_PLACE_ERROR_MESSAGE)
                                ->addViolation();
                        }
                    })
                ],
                'attr' => [
                    'data-parsley-required-message' => self::ACTUAL_DOSE_REQUIRED_ERROR_MESSAGE,
                    'data-parsley-gt-message' => self::ACTUAL_DOSE_GREATER_THAN_ERROR_MESSAGE,
                    'data-parsley-decimal-place-limit-message' => self::ACTUAL_DOSE_TENTH_PLACE_ERROR_MESSAGE,
                    'data-parsley-decimal-place-limit' => true,
                    'data-parsley-type' => 'number',
                    'data-parsley-gt' => self::WEIGHT_DOSE_GREATER_THAN,
                ],
            ])
            ->add('participantWeight', NumberType::class, [
                'required' => true,
                'label' => 'Participant Weight (kg)*',
                'empty_data' => 0,
                'constraints' => [
                    new NotBlank(),
                    new GreaterThan([
                        'value' => self::WEIGHT_DOSE_GREATER_THAN,
                        'message' => self::PARTICIPANT_WEIGHT_GREATER_THAN_ERROR_MESSAGE
                    ]),
                    new Callback(function ($value, $context) {
                        if ($value < self::WEIGHT_DOSE_GREATER_THAN || $value === null) {
                            $context->buildViolation(self::PARTICIPANT_WEIGHT_REQUIRED_ERROR_MESSAGE)
                                ->addViolation();
                        }
                        if ($value > self::PARTICIPANT_WEIGHT_LESS_THAN_WEIGHT) {
                            $context->buildViolation(self::PARTICIPANT_WEIGHT_LESS_THAN_ERROR_MESSAGE)
                                ->addViolation();
                        }
                        if ($this->getNumDecimalPlaces($value) > 1) {
                            $context->buildViolation(self::PARTICIPANT_WEIGHT_TENTH_PLACE_ERROR_MESSAGE)
                                ->addViolation();
                        }
                    })
                ],
                'attr' => [
                    'data-parsley-required-message' => self::PARTICIPANT_WEIGHT_REQUIRED_ERROR_MESSAGE,
                    'data-parsley-gt-message' => self::PARTICIPANT_WEIGHT_GREATER_THAN_ERROR_MESSAGE,
                    'data-parsley-lt-message' => self::PARTICIPANT_WEIGHT_LESS_THAN_ERROR_MESSAGE,
                    'data-parsley-decimal-place-limit-message' => self::PARTICIPANT_WEIGHT_TENTH_PLACE_ERROR_MESSAGE,
                    'data-parsley-decimal-place-limit' => true,
                    'data-parsley-type' => 'number',
                    'data-parsley-gt' => self::WEIGHT_DOSE_GREATER_THAN,
                    'data-parsley-lt' => self::PARTICIPANT_WEIGHT_LESS_THAN_WEIGHT,
                ]
            ])
            ->add('doseAdministered', DateTimeType::class, [
                'format' => 'M/d/yyyy h:mm a',
                'html5' => false,
                'required' => true,
                'widget' => 'single_text',
                'model_timezone' => 'UTC',
                'label' => 'Dose Date/Time',
                'attr' => [
                    'class' => 'order-ts',
                    'data-parsley-required-message' => self::DOSE_DATE_TIME_REQUIRED
                ],
                'view_timezone' => $options['timezone'],
                'constraints' => [
                    new Type('datetime'),
                    new LessThanOrEqual([
                        'value' => $constraintDate,
                        'message' => 'Date cannot be in the future'
                    ])
                ]
            ])
            ->add('calculatedDose', null, ['attr' => ['readonly' => true], 'mapped' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NphDlw::class,
            'allow_extra_fields' => true,
            'timezone' => 'UTC'
        ]);
    }

    private function getNumDecimalPlaces($num): int
    {
        if ((int) $num == $num) {
            return 0;
        } elseif (!is_numeric($num)) {
            return 0;
        }
        return strlen($num) - strrpos($num, '.') - 1;
    }
}
