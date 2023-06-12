<?php

namespace App\Form\Nph;

use App\Entity\NphSample;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class NphOrderType extends AbstractType
{
    private const STOOL_ST1 = 'ST1';
    private const TISSUE_CONSENT_SAMPLES = ['HAIR', 'NAILB', 'NAILL'];

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
                    } elseif ($options['module'] === '1' && $options['module1tissueCollectConsent'] === false
                        && in_array($val, self::TISSUE_CONSENT_SAMPLES)) {
                        $attr['disabled'] = true;
                        $attr['class'] = 'sample-disabled sample-disabled-colored';
                        $attr['checked'] = false;
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
                if ($timePoint === NphSample::PRE_LMT) {
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
