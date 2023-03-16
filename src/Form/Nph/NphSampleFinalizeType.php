<?php

namespace App\Form\Nph;

use App\Entity\NphOrder;
use App\Entity\NphSample;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class NphSampleFinalizeType extends NphOrderForm
{
    private const BARCODE_PREFIX_MC = 'MC';

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $sample = $options['sample'];
        $orderType = $options['orderType'];

        $this->addCollectedTimeAndNoteFields($builder, $options, $sample);

        $disableMetadataFields = $options['disableMetadataFields'] && $options['nphSample']->getModifyType() !==
            NphSample::UNLOCK;

        if ($orderType === NphOrder::TYPE_URINE) {
            $this->addUrineMetadataFields($builder, $disableMetadataFields);
        }

        if ($orderType === NphOrder::TYPE_STOOL) {
            $this->addStoolMetadataFields($builder, $disableMetadataFields);
        }

        $formData = $builder->getData();

        if (!empty($options['aliquots'])) {
            foreach ($options['aliquots'] as $aliquotCode => $aliquot) {
                $idData = $tsData = $volumeData = [];
                $aliquotCount = isset($formData[$aliquotCode]) ? count($formData[$aliquotCode]) : $aliquot['expectedAliquots'];
                for ($i = 0; $i < $aliquotCount; $i++) {
                    $idData[] = $formData[$aliquotCode][$i] ?? null;
                    $tsData[] = $formData["{$aliquotCode}AliquotTs"][$i] ?? null;
                    $volumeData[] = $formData["{$aliquotCode}Volume"][$i] ?? null;
                }
                $barcodePattern = '';
                if (!empty($aliquot['barcodePrefix'])) {
                    $barcodePattern = $aliquot['barcodePrefix'];
                }
                $barcodePattern = "{$barcodePattern}[0-9]{{$aliquot['barcodeLength']}}";
                $builder->add("{$aliquotCode}", Type\CollectionType::class, [
                    'entry_type' => Type\TextType::class,
                    'entry_options' => [
                        'constraints' => [
                            new Constraints\Type('string'),
                            new Constraints\Regex([
                                'pattern' => "/^{$barcodePattern}$/",
                                'message' => $this->getBarcodeErrorMessage($aliquot)
                            ]),
                            new Constraints\Callback(function ($value, $context) use ($aliquotCode, $aliquot) {
                                $formData = $context->getRoot()->getData();
                                $key = intval($context->getObject()->getName());
                                $condition = $aliquot['expectedVolume'] ? ($formData["{$aliquotCode}AliquotTs"][$key] ||
                                    $formData["{$aliquotCode}Volume"][$key]) : $formData["{$aliquotCode}AliquotTs"][$key];
                                if ($condition && empty($value)) {
                                    $context->buildViolation('Barcode is required')->addViolation();
                                }
                            })
                        ],
                        'attr' => [
                            'placeholder' => 'Scan Aliquot Barcode',
                            'class' => 'aliquot-barcode',
                            'data-barcode-length' => $aliquot['barcodeLength'],
                            'data-barcode-prefix' => $aliquot['barcodePrefix'] ?? null
                        ],
                    ],
                    'label' => $aliquot['container'],
                    'required' => false,
                    'allow_add' => true,
                    'allow_delete' => true,
                    'data' => $idData,
                ]);

                $builder->add("{$aliquotCode}AliquotTs", Type\CollectionType::class, [
                    'entry_type' => Type\DateTimeType::class,
                    'label' => false,
                    'entry_options' => [
                        'widget' => 'single_text',
                        'format' => 'M/d/yyyy h:mm a',
                        'html5' => false,
                        'view_timezone' => $options['timeZone'],
                        'model_timezone' => 'UTC',
                        'label' => false,
                        'constraints' => [
                            new Constraints\LessThanOrEqual([
                                'value' => new \DateTime('+5 minutes'),
                                'message' => 'Timestamp cannot be in the future'
                            ]),
                            new Constraints\Callback(function ($value, $context) use ($aliquotCode, $aliquot) {
                                $formData = $context->getRoot()->getData();
                                $key = intval($context->getObject()->getName());
                                $condition = $aliquot['expectedVolume'] ? ($formData[$aliquotCode][$key] ||
                                    $formData["{$aliquotCode}Volume"][$key]) : $formData[$aliquotCode][$key];
                                if ($condition && empty($value)) {
                                    $context->buildViolation('Time is required')->addViolation();
                                }
                            })
                        ],
                        'attr' => [
                            'class' => 'order-ts',
                        ]
                    ],
                    'required' => false,
                    'allow_add' => true,
                    'allow_delete' => true,
                    'data' => $tsData,
                ]);

                $volumeConstraints = [
                    new Constraints\Callback(function ($value, $context) use ($aliquotCode, $aliquot) {
                        $formData = $context->getRoot()->getData();
                        $key = intval($context->getObject()->getName());
                        if ($aliquot['expectedVolume'] && ($formData[$aliquotCode][$key] || $formData["{$aliquotCode}AliquotTs"][$key])
                            && $value === null) {
                            $context->buildViolation('Volume is required')->addViolation();
                        }
                        if ($aliquot['expectedVolume'] === null && !empty($value)) {
                            $context->buildViolation('Volume should not be entered')->addViolation();
                        }
                    })
                ];
                if (isset($aliquot['minVolume'])) {
                    $volumeConstraints[] = new Constraints\GreaterThan([
                        'value' => $aliquot['minVolume'],
                        'message' => 'Volume must be greater than 0'
                    ]);
                }
                if (isset($aliquot['maxVolume'])) {
                    $errorMessage = 'Please verify the volume is correct.';
                    if ($orderType === NphOrder::TYPE_BLOOD) {
                        $errorMessage .= ' If greater than expected volume, you may add an additional aliquot.';
                    }
                    $volumeConstraints[] = new Constraints\LessThanOrEqual([
                        'value' => $aliquot['maxVolume'],
                        'message' => $errorMessage
                    ]);
                }
                $builder->add("{$aliquotCode}Volume", Type\CollectionType::class, [
                    'entry_type' => Type\TextType::class,
                    'label' => 'Volume',
                    'entry_options' => [
                        'constraints' => $volumeConstraints,
                        'attr' => $this->getVolumeAttributes($aliquot)
                    ],
                    'required' => false,
                    'allow_add' => true,
                    'allow_delete' => true,
                    'data' => $volumeData,
                    'attr' => [
                        'readonly' => $aliquot['expectedVolume'] === null
                    ]
                ]);
            }
        }

        $nphSample = $options['nphSample'];
        if ($nphSample->getModifyType() === NphSample::UNLOCK) {
            $finalizedAliquots = $nphSample->getNphAliquots();
            foreach ($finalizedAliquots as $finalizedAliquot) {
                $builder->add(
                    "cancel_{$finalizedAliquot->getAliquotCode()}_{$finalizedAliquot->getAliquotId()}",
                    Type\CheckboxType::class,
                    [
                        'label' => false,
                        'required' => false,
                        'disabled' => $finalizedAliquot->getStatus() === NphSample::CANCEL,
                        'attr' => [
                            'class' => 'sample-modify-checkbox',
                        ]
                    ]
                );
                $builder->add(
                    "restore_{$finalizedAliquot->getAliquotCode()}_{$finalizedAliquot->getAliquotId()}",
                    Type\CheckboxType::class,
                    [
                        'label' => false,
                        'required' => false,
                        'disabled' => $finalizedAliquot->getStatus() !== NphSample::CANCEL,
                        'attr' => [
                            'class' => 'sample-modify-checkbox',
                        ]
                    ]
                );
            }
        }

        // Placeholder field for displaying enter at least one aliquot message
        $builder->add('aliquotError', Type\CheckboxType::class, [
            'required' => false
        ]);

        return $builder->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'sample' => null,
            'orderType' => null,
            'timeZone' => null,
            'aliquots' => null,
            'disabled' => null,
            'nphSample' => null,
            'disableMetadataFields' => null,
            'disableStoolCollectedTs' => null,
            'orderCreatedTs' => null
        ]);
    }

    private function getVolumeAttributes(array $aliquot): array
    {
        $volumeAttributes = [
            'class' => 'aliquot-volume',
            'data-expected-volume' => $aliquot['expectedVolume']
        ];
        if (isset($aliquot['warningMinVolume'])) {
            $volumeAttributes['data-warning-min-volume'] = $aliquot['warningMinVolume'];
        }
        if (isset($aliquot['warningMaxVolume'])) {
            $volumeAttributes['data-warning-max-volume'] = $aliquot['warningMaxVolume'];
        }
        return $volumeAttributes;
    }

    private function getBarcodeErrorMessage(array $aliquot): string
    {
        switch ($aliquot['barcodeLength']) {
            case 10:
                if (isset($aliquot['barcodePrefix']) && $aliquot['barcodePrefix'] === self::BARCODE_PREFIX_MC) {
                    return 'Please enter a valid aliquot barcode. Format should be MC1000000000 (MC + 10 digits).';
                }
                return 'Please enter a valid aliquot barcode. Format should be 1000000000 (10 digits).';
            case 11:
                return 'Please enter a valid aliquot barcode. Format should be 10000000000 (11 digits).';
            default:
                return 'Please enter a valid aliquot barcode.';
        }
    }
}
