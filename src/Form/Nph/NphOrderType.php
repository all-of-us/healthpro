<?php

namespace App\Form\Nph;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class NphOrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $ordersData = $builder->getData();
        $timePointSamples = $options['timePointSamples'];
        $timePoints = $options['timePoints'];
        $stoolSamples = $options['stoolSamples'];
        foreach ($timePointSamples as $timePoint => $samples) {
            foreach ($samples as $sampleCode => $sample) {
                if ($sampleCode === 'ST1') {
                    $builder->add('stoolKit', Type\TextType::class, [
                        'label' => 'Stool Kit ID',
                        'required' => false,
                        'constraints' => [
                            new Constraints\Type('string'),
                            new Constraints\Regex([
                                'pattern' => '/^KIT-[0-9]{8}$/',
                                'message' => 'Please enter a valid KIT ID. Format should be KIT-10000000 (KIT-8 digits)'
                            ]),
                            new Constraints\Callback(function ($value, $context) use ($stoolSamples) {
                                $formData = $context->getRoot()->getData();
                                if (empty($value)) {
                                    $hasStoolTube = false;
                                    foreach ($stoolSamples as $stoolSample) {
                                        if (!empty($formData[$stoolSample])) {
                                            $hasStoolTube = true;
                                        }
                                    }
                                    if ($hasStoolTube) {
                                        $context->buildViolation('Please enter Stool KIT ID')->addViolation();
                                    }
                                }
                            })
                        ],
                        'attr' => [
                            'placeholder' => 'Scan Kit ID',
                            'disabled' => !empty($ordersData['stoolKit'])
                        ]
                    ]);
                }
                if (in_array($sampleCode, $options['stoolSamples'])) {
                    $builder->add($sampleCode, Type\TextType::class, [
                        'label' => $sample,
                        'required' => false,
                        'disabled' => !empty($ordersData[$sampleCode]),
                        'constraints' => [
                            new Constraints\Type('string'),
                            new Constraints\Regex([
                                'pattern' => '/^[0-9]{11}$/',
                                'message' => 'Please enter a valid collection tube barcode.Format should be 10000000000 (11 digits).'
                            ])
                        ],
                        'attr' => [
                            'placeholder' => 'Scan Tube',
                            'disabled' => !empty($ordersData['stoolKit'])
                        ]
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
                'choice_attr' => function ($val) use ($ordersData, $timePoint) {
                    $attr = [];
                    if (isset($ordersData[$timePoint]) && in_array($val, $ordersData[$timePoint])) {
                        $attr['disabled'] = true;
                        $attr['class'] = 'sample-disabled';
                        $attr['checked'] = true;
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
            'stoolSamples' => null
        ]);
    }
}
