<?php

namespace App\Form\Nph;

use App\Entity\NphSample;
use App\Helper\NphParticipant;
use App\Nph\Order\Modules\Module1;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class NphOrderType extends AbstractType
{
    private const STOOL_ST1 = 'ST1';
    private const STOOL_KIT_FIELD = 'stoolKit';
    private const STOOL_KIT_FIELD_2 = 'stoolKit2';
    private const STOOL_KIT_TUBES = ['ST1', 'ST2', 'ST3', 'ST4'];
    private const STOOL_KIT_TUBES_2 = ['ST5', 'ST6', 'ST7', 'ST8'];
    private const TISSUE_CONSENT_SAMPLES = ['HAIR', 'NAILB', 'NAILL'];
    private const CONSENT_DISABLE_SAMPLE_ATTR = [
        'disabled' => true,
        'class' => 'sample-disabled sample-disabled-colored',
        'checked' => false
    ];
    private const STOOL_KIT_ID_PATTERN = '/^KIT-[0-9]{8}$/';
    private const STOOL_KIT_ID_PATTERN_ERROR_MESSAGE = 'Please enter a valid KIT ID. Format should include the prefix KIT- (Found on label on front of stool kit box).';
    private const STOOL_BARCODE_ID_PATTERN = '/^[0-9]{11}$/';
    private const STOOL_BARCODE_ID_PATTERN_ERROR_MESSAGE = 'Stool tube barcode ID invalid.  Please enter a valid stool tube barcode ID.';

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $ordersData = $builder->getData();
        $timePointSamples = $options['timePointSamples'];
        $timePoints = $options['timePoints'];
        $isStoolKitDisabled = !empty($ordersData['stoolKit']);
        $ordersKitData = $isStoolKitDisabled ? $ordersData['stoolKit'] : null;
        foreach ($timePointSamples as $timePoint => $samples) {
            foreach ($samples as $sampleCode => $sample) {
                if ($sampleCode === self::STOOL_ST1) {
                    $this->addStoolKitField($builder, $isStoolKitDisabled, $ordersKitData, self::STOOL_KIT_FIELD);
                }
                if ($options['module'] === '3') {
                    $this->addStoolKitField($builder, $isStoolKitDisabled, $ordersKitData, self::STOOL_KIT_FIELD_2);
                }
                if (in_array($sampleCode, $options['stoolSamples'])) {
                    $stoolTubeAttributes = [
                        'class' => 'stool-id tube-id',
                        'placeholder' => 'Scan Tube',
                        'disabled' => $isStoolKitDisabled,
                        'data-parsley-pattern' => self::STOOL_BARCODE_ID_PATTERN,
                        'data-parsley-pattern-message' => self::STOOL_BARCODE_ID_PATTERN_ERROR_MESSAGE,
                        'data-parsley-unique' => $sampleCode,
                        'data-parsley-unique-message' => 'Please enter unique Stool Tube IDs.',
                        'data-stool-type' => 'tube'
                    ];
                    if ($isStoolKitDisabled && isset($ordersData[$sampleCode])) {
                        $stoolTubeAttributes['value'] = $ordersData[$sampleCode];
                    }
                    $stoolKitField = in_array($sampleCode, self::STOOL_KIT_TUBES) ? self::STOOL_KIT_FIELD : self::STOOL_KIT_FIELD_2;
                    $builder->add($sampleCode, Type\TextType::class, [
                        'label' => $sample,
                        'required' => false,
                        'constraints' => [
                            new Constraints\Type('string'),
                            new Constraints\Regex([
                                'pattern' => self::STOOL_BARCODE_ID_PATTERN,
                                'message' => self::STOOL_BARCODE_ID_PATTERN_ERROR_MESSAGE
                            ]),
                            new Constraints\Callback(function ($value, $context) use ($stoolKitField) {
                                $formData = $context->getRoot()->getData();
                                if ($this->isStoolChecked($formData, $stoolKitField) && empty($value)) {
                                    $context->buildViolation('Please enter Stool Tube ID')->addViolation();
                                }
                            })
                        ],
                        'attr' => $stoolTubeAttributes
                    ]);
                    unset($samples[$sampleCode]);
                }
            }
            $builder->add($timePoint, Type\ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => $timePoints[$timePoint],
                'choices' => array_flip($samples),
                'required' => false,
                'choice_attr' => function ($val) use ($ordersData, $timePoint, $options) {
                    $attr = [];
                    if (isset($ordersData[$timePoint]) && in_array($val, $ordersData[$timePoint])) {
                        $attr['disabled'] = true;
                        $attr['class'] = 'sample-disabled';
                        $attr['checked'] = true;
                    } elseif ($options['module'] === '1' && in_array($val, self::TISSUE_CONSENT_SAMPLES)) {
                        switch ($options['module1tissueCollectConsent']) {
                            case NphParticipant::OPTIN_DENY:
                                $attr = self::CONSENT_DISABLE_SAMPLE_ATTR;
                                break;
                            case NphParticipant::OPTIN_HAIR:
                                if (in_array($val, Module1::SAMPLE_CONSENT_TYPE_NAIL)) {
                                    $attr = self::CONSENT_DISABLE_SAMPLE_ATTR;
                                }
                                break;
                            case NphParticipant::OPTIN_NAIL:
                                if (in_array($val, Module1::SAMPLE_CONSENT_TYPE_HAIR)) {
                                    $attr = self::CONSENT_DISABLE_SAMPLE_ATTR;
                                }
                                break;
                            default:
                                break;
                        }
                    }
                    return $attr;
                }
            ]);
        }
        $builder->add('validate', Type\SubmitType::class, [
            'label' => 'Next',
            'attr' => [
                'class' => 'btn btn-primary'
            ]
        ]);
        // Placeholder field for displaying select at least one sample message
        $builder->add('checkAll', Type\CheckboxType::class, [
            'required' => false
        ]);

        $builder->add('downtime_generated', Type\CheckboxType::class, [
            'required' => false,
            'constraints' => [
                new Constraints\Callback(function ($value, $context) {
                    $formData = $context->getRoot()->getData();
                    if ($value && empty($formData['createdTs'])) {
                        $context->buildViolation('Please enter a downtime generation time')->addViolation();
                    }
                    if ($formData['createdTs'] > new \DateTime()) {
                        $context->buildViolation('Generation time cannot be in the future')->addViolation();
                    }
                })
            ]
        ]);

        $builder->add('createdTs', DateTimeType::class, [
        'format' => 'M/d/yyyy h:mm a',
        'html5' => false,
        'required' => false,
        'widget' => 'single_text',
        'view_timezone' => $options['userTimezone'],
        'model_timezone' => 'UTC',
        'label' => 'Order Creation Time',
        'attr' => ['class' => 'order-ts', 'autocomplete' => 'off'],
        ]);

        return $builder->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'timePointSamples' => null,
            'timePoints' => null,
            'stoolSamples' => null,
            'module' => null,
            'module1tissueCollectConsent' => null,
            'userTimezone' => null,
        ]);
    }

    private function isStoolChecked(array $formData, string $fieldName): bool
    {
        $sampleStool = $fieldName === self::STOOL_KIT_FIELD ? NphSample::SAMPLE_STOOL : NphSample::SAMPLE_STOOL_2;
        foreach ($formData as $timePoint => $samples) {
            if (!empty($samples) && is_array($samples)) {
                if (in_array($timePoint, NphSample::STOOL_TIMEPOINTS)) {
                    foreach ($samples as $sample) {
                        if ($sample === $sampleStool) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    private function addStoolKitField(&$builder, $isStoolKitDisabled, $stoolKitData, $fieldName): void
    {
        $stoolKitAttributes = [
            'class' => 'stool-id',
            'placeholder' => 'Scan Kit ID',
            'disabled' => $isStoolKitDisabled,
            'data-parsley-pattern' => self::STOOL_KIT_ID_PATTERN,
            'data-parsley-pattern-message' => self::STOOL_KIT_ID_PATTERN_ERROR_MESSAGE,
            'data-stool-type' => 'kit'
        ];
        if ($isStoolKitDisabled) {
            $stoolKitAttributes['value'] = $stoolKitData;
        }
        $builder->add($fieldName, Type\TextType::class, [
            'label' => 'Stool Kit ID',
            'required' => false,
            'constraints' => [
                new Constraints\Type('string'),
                new Constraints\Regex([
                    'pattern' => self::STOOL_KIT_ID_PATTERN,
                    'message' => self::STOOL_KIT_ID_PATTERN_ERROR_MESSAGE
                ]),
                new Constraints\Callback(function ($value, $context) use ($fieldName) {
                    $formData = $context->getRoot()->getData();
                    if ($this->isStoolChecked($formData, $fieldName) && empty($value)) {
                        $context->buildViolation('Please enter Stool KIT ID')->addViolation();
                    }
                })
            ],
            'attr' => $stoolKitAttributes
        ]);
    }
}
