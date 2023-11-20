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
use Symfony\Component\Validator\Constraints\NotBlank;

class NphOrderType extends AbstractType
{
    private const STOOL_ST1 = 'ST1';
    private const TISSUE_CONSENT_SAMPLES = ['HAIR', 'NAILB', 'NAILL'];
    private const CONSENT_DISABLE_SAMPLE_ATTR = [
        'disabled' => true,
        'class' => 'sample-disabled sample-disabled-colored',
        'checked' => false
    ];

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $ordersData = $builder->getData();
        $timePointSamples = $options['timePointSamples'];
        $timePoints = $options['timePoints'];
        $isStoolKitDisabled = !empty($ordersData['stoolKit']);
        foreach ($timePointSamples as $timePoint => $samples) {
            foreach ($samples as $sampleCode => $sample) {
                if ($sampleCode === self::STOOL_ST1) {
                    $stoolKitAttributes = [
                        'placeholder' => 'Scan Kit ID',
                        'disabled' => $isStoolKitDisabled,
                    ];
                    if ($isStoolKitDisabled) {
                        $stoolKitAttributes['value'] = $ordersData['stoolKit'];
                    }
                    $builder->add('stoolKit', Type\TextType::class, [
                        'label' => 'Stool Kit ID',
                        'required' => false,
                        'constraints' => [
                            new Constraints\Type('string'),
                            new Constraints\Regex([
                                'pattern' => '/^KIT-[0-9]{8}$/',
                                'message' => 'Please enter a valid KIT ID. Format should include the prefix KIT- (Found on label on front of stool kit box).'
                            ]),
                            new Constraints\Callback(function ($value, $context) {
                                $formData = $context->getRoot()->getData();
                                if ($this->isStoolChecked($formData) && empty($value)) {
                                    $context->buildViolation('Please enter Stool KIT ID')->addViolation();
                                }
                            })
                        ],
                        'attr' => $stoolKitAttributes
                    ]);
                }
                if (in_array($sampleCode, $options['stoolSamples'])) {
                    $stoolTubeAttributes = [
                        'placeholder' => 'Scan Tube',
                        'disabled' => $isStoolKitDisabled,
                    ];
                    if ($isStoolKitDisabled && isset($ordersData[$sampleCode])) {
                        $stoolTubeAttributes['value'] = $ordersData[$sampleCode];
                    }
                    $builder->add($sampleCode, Type\TextType::class, [
                        'label' => $sample,
                        'required' => false,
                        'constraints' => [
                            new Constraints\Type('string'),
                            new Constraints\Regex([
                                'pattern' => '/^[0-9]{11}$/',
                                'message' => 'Stool tube barcode ID invalid.  Please enter a valid stool tube barcode ID.'
                            ]),
                            new Constraints\Callback(function ($value, $context) {
                                $formData = $context->getRoot()->getData();
                                if ($this->isStoolChecked($formData) && empty($value)) {
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
        ]);

        $builder->add('createdTs', DateTimeType::class, [
        'format' => 'M/d/yyyy h:mm a',
        'html5' => false,
        'required' => false,
        'widget' => 'single_text',
        'model_timezone' => 'UTC',
        'label' => 'Dose Date/Time',
        'attr' => ['class' => 'order-ts'],
        'constraints' => new NotBlank(['message' => 'Order Generation Time is required.'])
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
        ]);
    }

    private function isStoolChecked(array $formData): bool
    {
        foreach ($formData as $timePoint => $samples) {
            if (!empty($samples) && is_array($samples)) {
                if (in_array($timePoint, NphSample::STOOL_TIMEPOINTS)) {
                    foreach ($samples as $sample) {
                        if ($sample === NphSample::SAMPLE_STOOL) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }
}
