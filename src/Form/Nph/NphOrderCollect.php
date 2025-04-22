<?php

namespace App\Form\Nph;

use App\Entity\NphOrder;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class NphOrderCollect extends NphOrderForm
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $samples = $options['samples'];
        $orderType = $options['orderType'];
        $sampleIndex = 1;

        if ($options['showOrderGenerationTimeField']) {
            $constraints = $this->getDateTimeConstraints();
            array_push(
                $constraints,
                new Constraints\NotBlank([
                    'message' => 'Order Generation time is required'
                ])
            );
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
                ]
            ]);
        }

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
                ],
                'disabled' => $sample['disabled']
            ]);
            $this->addCollectedTimeAndNoteFields($builder, $options, $sampleCode, $sample['disabled'], 'collect');
            $sampleIndex++;
        }

        if ($options['showMetadataFields'] && ($orderType === NphOrder::TYPE_URINE || $orderType === NphOrder::TYPE_24URINE)) {
            $this->addUrineMetadataFields($builder, $options['disableMetadataFields'], NphOrderForm::FORM_COLLECT_TYPE);
        }

        if ($options['showVolumeFields'] && $orderType === NphOrder::TYPE_24URINE) {
            $this->addUrineTotalCollectionVolume($builder, $options['disableMetadataFields']);
        }

        if ($orderType === NphOrder::TYPE_STOOL || $orderType === NphOrder::TYPE_STOOL_2) {
            $constraints = $this->getDateTimeConstraints();
            array_push(
                $constraints,
                new Constraints\NotBlank([
                    'message' => 'Collection time is required'
                ]),
                $this->getCollectedTimeGreaterThanConstraint($options['orderCreatedTs'])
            );
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
                    'readonly' => $options['disableStoolCollectedTs']
                ]
            ]);
            if ($options['showMetadataFields']) {
                $this->addStoolMetadataFields($builder, $options['timeZone'], $orderType, $options['disableMetadataFields'], false, NphOrderForm::FORM_COLLECT_TYPE);
            }
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
            'biobankView' => false,
            'showOrderGenerationTimeField' => false,
            'showMetadataFields' => true,
            'showVolumeFields' => true
        ]);
    }
}
