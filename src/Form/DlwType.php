<?php

namespace App\Form;

use App\Entity\NphDlw;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class DlwType extends AbstractType
{
    private const DOSE_BATCH_ID_DIGITS = 8;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $doseBatchIdErrorMessage = 'Dose Batch ID invalid, Please enter a valid dose batch ID (' . $this::DOSE_BATCH_ID_DIGITS . ' digits).';
        $builder
            ->add('doseBatchId', NumberType::class, [
                'label' => 'Dose Batch ID',
                'constraints' => [
                    new NotBlank(),
                    new Length([
                        'min' => $this::DOSE_BATCH_ID_DIGITS,
                        'max' => $this::DOSE_BATCH_ID_DIGITS,
                        'exactMessage' => $doseBatchIdErrorMessage
                    ])
                ],
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'data-parsley-required-message' => 'Dose batch ID required.'
                ],
            ])
            ->add('actualDose', NumberType::class, [
                'label' => 'Actual Dose (g)*',
                'required' => true,
                'empty_data' => 0,
                'constraints' => [
                    new NotBlank(),
                    new GreaterThan([
                        'value' => 0,
                        'message' => 'Dose must be greater than 0.'
                    ]),
                    new Callback(function ($value, $context) {
                        if ($this->getNumDecimalPlaces($value) > 1) {
                            $context->buildViolation('Please verify the measurement is correct. Value can be entered up to the tenths (0.1) place.')
                                ->addViolation();
                        }
                    })
                ],
                'attr' => [
                    'data-parsley-required-message' => 'Actual dose required.'
                ],
            ])
            ->add('participantWeight', NumberType::class, [
                'required' => true,
                'label' => 'Participant Weight (kg)*',
                'empty_data' => 0,
                'constraints' => [
                    new NotBlank(),
                    new GreaterThan([
                        'value' => 0,
                        'message' => 'Please verify the measurement is correct. Value should be greater than 0 kg.'
                    ]),
                    new Callback(function ($value, $context) {
                        if ($value < 0 || $value === null) {
                            $context->buildViolation('Participant weight required.')
                                ->addViolation();
                        }
                        if ($value > 907) {
                            $context->buildViolation('Please verify the measurement is correct. Value should be less than 907 kg.')
                                ->addViolation();
                        }
                        if ($this->getNumDecimalPlaces($value) > 1) {
                            $context->buildViolation('Please verify the measurement is correct. Value can be entered up to the tenths (0.1) place.')
                                ->addViolation();
                        }
                    })
                ],
                'attr' => [
                    'data-parsley-required-message' => 'Participant weight required.'
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
                    'data-parsley-required-message' => 'Dose date/time required.'
                ],
                'view_timezone' => $options['timezone'],
                'constraints' => new NotBlank(['message' => 'Dose date/time required.'])
            ])
            ->add('calculatedDose', null, ['attr' => ['readonly' => true], 'mapped' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NphDlw::class,
            'allow_extra_fields' => true,
            'timezone' => 'UTC',
        ]);
    }

    private function getNumDecimalPlaces($num): int
    {
        if ((int)$num == $num) {
            return 0;
        } elseif (!is_numeric($num)) {
            return 0;
        }
        return strlen($num) - strrpos($num, '.') - 1;
    }
}
