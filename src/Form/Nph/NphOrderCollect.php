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
        foreach ($samples as $sampleId => $sample) {
            $sampleCode = $sample['sampleCode'];
            $sampleLabel = "({$sampleIndex}) {$sample['label']} ({$sampleId})";
            $builder->add($sampleCode . $sampleId, Type\CheckboxType::class, [
                'label' => $sampleLabel,
                'required' => false,
                'constraints' => [
                    new Constraints\Callback(function ($value, $context) use ($sampleCode, $orderType, $sampleId) {
                        if ($orderType !== NphOrder::TYPE_STOOL && $value === false && !empty($context->getRoot()
                            ["{$sampleCode}{$sampleId}CollectedTs"]->getData())) {
                            $context->buildViolation('Collected sample required')->addViolation();
                        }
                    })
                ],
                'attr' => [
                    'data-sample-id' => $sampleId,
                ],
                'disabled' => $sample['disabled']
            ]);
            $this->addCollectedTimeAndNoteFields($builder, $options, $sampleCode, $sampleId, $sample['disabled'], 'collect');
            $sampleIndex++;
        }

        if ($orderType === NphOrder::TYPE_URINE) {
            $this->addUrineMetadataFields($builder, $options['disableMetadataFields']);
        }

        if ($orderType === NphOrder::TYPE_STOOL) {
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
                'format' => 'M/d/yyyy h:mm a',
                'html5' => false,
                'model_timezone' => 'UTC',
                'view_timezone' => $options['timeZone'],
                'attr' => [
                    'class' => 'order-ts',
                    'readonly' => $options['disableStoolCollectedTs']
                ]
            ]);
            $this->addStoolMetadataFields($builder, $options['disableMetadataFields']);
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
            'orderCreatedTs' => null
        ]);
    }
}
