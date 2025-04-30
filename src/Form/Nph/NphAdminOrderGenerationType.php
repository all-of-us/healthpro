<?php

namespace App\Form\Nph;

use App\Entity\NphOrder;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class NphAdminOrderGenerationType extends NphOrderForm
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $samples = $options['samples'];
        $orderType = $options['orderType'];
        $sampleIndex = 1;

        $constraints = $this->getDateTimeConstraints();
        $constraints[] = new Constraints\NotBlank([
            'message' => 'Order Generation time is required'
        ]);
        $builder->add("{$orderType}GenerationTs", Type\DateTimeType::class, [
            'required' => false,
            'constraints' => $constraints,
            'label' => 'Order Generation Time',
            'widget' => 'single_text',
            'format' => 'MM/dd/yyyy h:mm a',
            'html5' => false,
            'model_timezone' => 'UTC',
            'view_timezone' => $options['timeZone'],
            'attr' => [
                'class' => 'order-ts',
                'autocomplete' => 'off'
            ]
        ]);

        foreach ($samples as $sampleCode => $sample) {
            $sampleLabel = "({$sampleIndex}) {$sample['label']} ({$sample['id']})";
            $builder->add($sampleCode, Type\CheckboxType::class, [
                'label' => $sampleLabel,
                'required' => false,
                'constraints' => [
                    new Constraints\Callback(function ($value, $context) use ($sampleCode, $orderType) {
                        if ($orderType !== NphOrder::TYPE_STOOL && $orderType !== NphOrder::TYPE_STOOL_2 && $value === false && !empty($context->getRoot()
                            ["{$sampleCode}CollectedTs"]->getData())) {
                            $context->buildViolation('Collected sample required')->addViolation();
                        }
                    })
                ],
                'attr' => [
                    'data-sample-id' => $sample['id'],
                    'data-sample-cancelled' => $sample['cancelled'],
                    'checked' => true
                ]
            ]);
            $constraints = $this->getDateTimeConstraints();
            if ($sample['finalized']) {
                $constraints[] = new Constraints\Callback(function ($value, $context) use ($sampleCode) {
                    if (empty($value) && $context->getRoot()[$sampleCode]->getData() === true) {
                        $context->buildViolation('Collection time is required')->addViolation();
                    }
                });
            }
            if ($orderType !== NphOrder::TYPE_STOOL && $orderType !== NphOrder::TYPE_STOOL_2) {
                $constraints[] = $this->getCollectionGenerationTimeConstraints($orderType);
                $builder->add("{$sampleCode}CollectedTs", Type\DateTimeType::class, [
                    'required' => false,
                    'label' => 'Collection Time',
                    'widget' => 'single_text',
                    'format' => 'MM/dd/yyyy h:mm a',
                    'html5' => false,
                    'model_timezone' => 'UTC',
                    'view_timezone' => $options['timeZone'],
                    'constraints' => $constraints,
                    'attr' => [
                        'class' => 'order-ts',
                        'readonly' => $options['disableStoolCollectedTs'],
                        'data-sample-cancelled' => $sample['cancelled'],
                        'autocomplete' => 'off'
                    ]
                ]);
            }
            $builder->add("{$sampleCode}Notes", Type\TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'constraints' => new Constraints\Type('string'),
                'attr' => [
                    'data-sample-cancelled' => $sample['cancelled'],
                ]
            ]);
            $sampleIndex++;
        }

        if ($orderType === NphOrder::TYPE_STOOL || $orderType === NphOrder::TYPE_STOOL_2) {
            $constraints = $this->getDateTimeConstraints();
            $constraints[] = $this->getCollectionGenerationTimeConstraints($orderType);
            $builder->add("{$orderType}CollectedTs", Type\DateTimeType::class, [
                'required' => true,
                'constraints' => $constraints,
                'label' => 'Collection Time',
                'widget' => 'single_text',
                'format' => 'MM/dd/yyyy h:mm a',
                'html5' => false,
                'model_timezone' => 'UTC',
                'view_timezone' => $options['timeZone'],
                'attr' => [
                    'class' => 'order-ts',
                    'readonly' => $options['disableStoolCollectedTs'],
                    'autocomplete' => 'off'
                ]
            ]);
        }

        // Placeholder field for displaying select at least one sample error message
        $builder->add('samplesCheckAll', Type\CheckboxType::class, [
            'required' => false
        ]);

        return $builder->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'samples' => null,
            'orderType' => null,
            'timeZone' => null,
            'disableMetadataFields' => null,
            'disableStoolCollectedTs' => null,
            'orderCreatedTs' => null,
            'biobankView' => false
        ]);
    }

    private function getCollectionGenerationTimeConstraints(string $orderType): Constraints\Callback
    {
        return new Constraints\Callback(function ($value, $context) use ($orderType) {
            $formData = $context->getRoot()->getData();
            if (!empty($formData["{$orderType}GenerationTs"]) && !empty($value)) {
                if ($value <= $formData["{$orderType}GenerationTs"]) {
                    $context->buildViolation('Collection time must be after order generation time')->addViolation();
                }
            }
        });
    }
}
