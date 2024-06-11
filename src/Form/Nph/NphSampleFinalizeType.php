<?php

namespace App\Form\Nph;

use App\Entity\NphOrder;
use App\Entity\NphSample;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class NphSampleFinalizeType extends NphOrderForm
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $sample = $options['sample'];
        $orderType = $options['orderType'];

        $this->addCollectedTimeAndNoteFields($builder, $options, $sample);

        $disableMetadataFields = $options['disableMetadataFields'] && $options['nphSample']->getModifyType() !==
            NphSample::UNLOCK;

        if ($orderType === NphOrder::TYPE_URINE || $orderType === NPHOrder::TYPE_24URINE) {
            $this->addUrineMetadataFields($builder, $disableMetadataFields);
        }

        if ($orderType === NphOrder::TYPE_STOOL) {
            $this->addStoolMetadataFields($builder, $options['timeZone'], $sample, $disableMetadataFields, $options['disableFreezeTs']);
        }

        if ($orderType === NphOrder::TYPE_24URINE) {
            $this->addUrineTotalCollectionVolume($builder, $disableMetadataFields);
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
                                'message' => $aliquot['barcodeErrorMessage']
                            ]),
                            new Constraints\Callback(function ($value, $context) use ($aliquotCode, $aliquot) {
                                $formData = $context->getRoot()->getData();
                                $key = intval($context->getObject()->getName());
                                $condition = $aliquot['expectedVolume'] ? ($formData["{$aliquotCode}AliquotTs"][$key] ||
                                    $formData["{$aliquotCode}Volume"][$key]) : $formData["{$aliquotCode}AliquotTs"][$key];
                                if ($condition && empty($value)) {
                                    $context->buildViolation('Aliquot barcode is required')->addViolation();
                                }
                            }),
                            new Constraints\Callback(function ($value, $context) use ($aliquot, $aliquotCode) {
                                if ($aliquot['required'] ?? false) {
                                    $requiredFilled = false;
                                    foreach ($context->getRoot()->getData()[$aliquotCode] as $key => $aliquotId) {
                                        if (!empty($aliquotId)) {
                                            $requiredFilled = true;
                                        }
                                    }
                                    if (!$requiredFilled) {
                                        $context->buildViolation("At least one {$aliquot['expectedVolume']}{$aliquot['units']} aliquot is required")->addViolation();
                                    }
                                }
                            })
                        ],
                        'attr' => [
                            'placeholder' => 'Scan Aliquot Barcode',
                            'class' => 'aliquot-barcode',
                            'data-barcode-length' => $aliquot['barcodeLength'],
                            'data-barcode-prefix' => $aliquot['barcodePrefix'] ?? null,
                            'data-parsley-pattern' => $barcodePattern,
                            'data-parsley-pattern-message' => $aliquot['barcodeErrorMessage']
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
                            new Constraints\Callback(function ($value, $context) use ($aliquotCode, $aliquot, $sample) {
                                $formData = $context->getRoot()->getData();
                                $key = intval($context->getObject()->getName());
                                $condition = $aliquot['expectedVolume'] ? ($formData[$aliquotCode][$key] ||
                                    $formData["{$aliquotCode}Volume"][$key]) : $formData[$aliquotCode][$key];
                                if ($condition && empty($value)) {
                                    $context->buildViolation('Aliquot time is required')->addViolation();
                                }
                                if (!empty($formData["{$sample}CollectedTs"]) && !empty($value)) {
                                    if ($value <= $formData["{$sample}CollectedTs"]) {
                                        $context->buildViolation('Aliquot time must be after collection time')->addViolation();
                                    }
                                }
                            })
                        ],
                        'attr' => [
                            'class' => 'order-ts aliquot-ts',
                            'data-field-type' => 'aliquot',
                            'data-parsley-aliquot-date-comparison' => "nph_sample_finalize_{$sample}CollectedTs"
                        ]
                    ],
                    'required' => false,
                    'allow_add' => true,
                    'allow_delete' => true,
                    'data' => $tsData,
                ]);
                if (isset($aliquot['collectMetadata']) && $aliquot['collectMetadata']) {
                    foreach ($aliquot['metadataFields'] as $metadataField) {
                        if ($metadataField['identifier'] === 'glycerolAdditiveVolume') {
                            $metadataConstraints = [
                                new Constraints\Callback(function ($value, $context) use ($aliquotCode, $metadataField) {
                                    $key = intval($context->getObject()->getName());
                                    $formData = $context->getRoot()->getData();
                                    $glycerolVolume = $formData[$aliquotCode . $metadataField['identifier']][$key];
                                    if (isset($formData[$aliquotCode][$key])) {
                                        if ($glycerolVolume === null) {
                                            $context->buildViolation('Glycerol Volume: Volume is required')->addViolation();
                                        } elseif ($glycerolVolume === 0) {
                                            $context->buildViolation('Glycerol Volume: Volume must be greater than 0')->addViolation();
                                        } elseif ($glycerolVolume > $metadataField['maxVolume']) {
                                            $context->buildViolation("Glycerol Volume: Please verify the volume is correct. This aliquot should contain a maximum of {$metadataField['maxVolume']} {$metadataField['units']}.")->atPath($aliquotCode . $metadataField['identifier'])->addViolation();
                                        }
                                    }
                                })
                            ];
                            $metadataValue = $formData["{$aliquotCode}glycerolAdditiveVolume"] ?? [null];
                        } else {
                            $metadataValue = [null];
                        }
                        $builder->add("{$aliquotCode}{$metadataField['identifier']}", Type\CollectionType::class, [
                            'entry_type' => Type\TextType::class,
                            'entry_options' => [
                                'label' => $metadataField['label'],
                                'required' => false,
                                'attr' => [
                                    'placeholder' => $metadataField['placeholder'] ?? '',
                                    'class' => $metadataField['class'] ?? '',
                                    'data-parsley-max' => $metadataField['maxVolume'],
                                    'data-parsley-max-message' => "Glycerol Volume: Please verify the volume is correct. This aliquot should contain a maximum of {$metadataField['maxVolume']} {$metadataField['units']}."
                                ],
                                'constraints' => $metadataConstraints ?? [],
                            ],
                            'allow_add' => true,
                            'allow_delete' => true,
                            'data' => $metadataValue,
                        ]);
                    }
                }


                $volumeConstraints = [
                    new Constraints\Callback(function ($value, $context) use ($aliquotCode, $aliquot) {
                        $formData = $context->getRoot()->getData();
                        $key = intval($context->getObject()->getName());
                        if ($aliquot['expectedVolume'] && ($formData[$aliquotCode][$key] || $formData["{$aliquotCode}AliquotTs"][$key])
                            && $value === null) {
                            $errorMessage = 'Volume is required';
                            if (isset($aliquot['errorMessageVolumePrefix'])) {
                                $errorMessage = "{$aliquot['errorMessageVolumePrefix']} {$errorMessage}";
                            }
                            $context->buildViolation($errorMessage)->addViolation();
                        }
                        if ($aliquot['expectedVolume'] === null && !empty($value)) {
                            $errorMessage = 'Volume should not be entered';
                            if (isset($aliquot['errorMessageVolumePrefix'])) {
                                $errorMessage = "{$aliquot['errorMessageVolumePrefix']} {$errorMessage}";
                            }
                            $context->buildViolation('Volume should not be entered')->addViolation();
                        }
                    })
                ];
                if (isset($aliquot['minVolume'])) {
                    $errorMessage = 'Volume must be greater than 0';
                    if (isset($aliquot['errorMessageVolumePrefix'])) {
                        $errorMessage = "{$aliquot['errorMessageVolumePrefix']} {$errorMessage}";
                    }
                    $volumeConstraints[] = new Constraints\GreaterThan([
                        'value' => $aliquot['minVolume'],
                        'message' => $errorMessage
                    ]);
                }
                if (isset($aliquot['maxVolume'])) {
                    $errorMessage = "Please verify the volume is correct.  This aliquot should contain a maximum of {$aliquot['maxVolume']} {$aliquot['units']}.";
                    if (isset($aliquot['errorMessageVolumePrefix'])) {
                        $errorMessage = "{$aliquot['errorMessageVolumePrefix']} {$errorMessage}";
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
                            'class' => 'sample-cancel-checkbox',
                        ]
                    ]
                );
                $builder->add(
                    "restore_{$finalizedAliquot->getAliquotCode()}_{$finalizedAliquot->getAliquotId()}",
                    Type\CheckboxType::class,
                    [
                        'label' => false,
                        'required' => false,
                        'disabled' => $finalizedAliquot->getStatus() !== NphSample::CANCEL
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
            'orderCreatedTs' => null,
            'module' => null,
            'disableFreezeTs' => null
        ]);
    }

    private function getVolumeAttributes(array $aliquot): array
    {
        $volumeAttributes = [
            'class' => 'aliquot-volume',
            'data-expected-volume' => $aliquot['expectedVolume']
        ];
        if (isset($aliquot['maxVolume'])) {
            $volumeAttributes['data-parsley-max'] = $aliquot['maxVolume'];
            $errorMessage = "Please verify the volume is correct. This aliquot should contain a maximum of {$aliquot['maxVolume']} {$aliquot['units']}.";
            if (isset($aliquot['errorMessageVolumePrefix'])) {
                $errorMessage = "{$aliquot['errorMessageVolumePrefix']} {$errorMessage}";
            }
            $volumeAttributes['data-parsley-max-message'] = $errorMessage;
        }
        if (isset($aliquot['warningMinVolume'])) {
            $volumeAttributes['data-warning-min-volume'] = $aliquot['warningMinVolume'];
        }
        if (isset($aliquot['warningMaxVolume'])) {
            $volumeAttributes['data-warning-max-volume'] = $aliquot['warningMaxVolume'];
        }
        return $volumeAttributes;
    }
}
