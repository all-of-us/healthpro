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
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class DlwType extends AbstractType
{
    private const DOSE_BATCH_ID_DIGITS = 8;
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('doseBatchId', TextType::class, [
                'label' => 'Dose Batch ID',
                'constraints' => new Range([
                    'min' => 10 ** ($this::DOSE_BATCH_ID_DIGITS - 1),
                    'max' => (10 ** $this::DOSE_BATCH_ID_DIGITS) - 1,
                    'notInRangeMessage' => 'Dose Batch ID invalid, Please enter a valid dose batch ID (' . $this::DOSE_BATCH_ID_DIGITS . ' digits).',
                ]),
                'required' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('actualDose', NumberType::class, [
                'label' => 'Actual Dose (g)*',
                'required' => true,
                'scale' => 1,
            ])
            ->add('participantWeight', NumberType::class, [
                'required' => true,
                'label' => 'Participant Weight (kg)*',
                'empty_data' => 0,
                'constraints' => new Callback(function ($value, $context) {
                    if ($value < 0 || $value === null) {
                        $context->buildViolation('Participant weight required.')
                            ->addViolation();
                        return false;
                    }
                    if ($value > 907) {
                        $context->buildViolation('Please verify the measurement is correct. Value should be less than 907 kg.')
                            ->addViolation();
                        return  false;
                    }
                    if ($this->getNumDecimalPlaces($value) > 1) {
                        $context->buildViolation('Please verify the measurement is correct. Value can be entered up to the tenths (0.1) place.')
                            ->addViolation();
                        return false;
                    }
                    return true;
                }),
                'scale' => 1,
            ])
            ->add('doseAdministered', DateTimeType::class, [
                'format' => 'M/d/yyyy h:mm a',
                'html5' => false,
                'required' => true,
                'widget' => 'single_text',
                'model_timezone' => 'UTC',
                'label' => 'Dose Date/Time',
                'attr' => ['class' => 'order-ts'],
                'view_timezone' => $options['timezone'],
                'constraints' => new NotBlank(['message' => 'Dose date/time required.'])
            ])
            ->add('calculatedDose', null, ['attr' => ['readonly' => true], 'mapped' => false])
        ;
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
        if ((int) $num == $num) {
            return 0;
        } elseif (!is_numeric($num)) {
            return 0;
        }
        return strlen($num) - strrpos($num, '.') - 1;
    }
}
