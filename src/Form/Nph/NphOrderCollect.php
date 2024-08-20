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

        if ($orderType === NphOrder::TYPE_URINE || $orderType === NphOrder::TYPE_24URINE) {
            $this->addUrineMetadataFields($builder, $options['disableMetadataFields'], NphOrderForm::FORM_COLLECT_TYPE);
        }

        if ($orderType === NphOrder::TYPE_24URINE) {
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
                'format' => 'M/d/yyyy h:mm a',
                'html5' => false,
                'model_timezone' => 'UTC',
                'view_timezone' => $options['timeZone'],
                'attr' => [
                    'class' => 'order-ts',
                    'readonly' => $options['disableStoolCollectedTs']
                ]
            ]);
            $this->addStoolMetadataFields($builder, $options['timeZone'], $orderType, $options['disableMetadataFields'], false, NphOrderForm::FORM_COLLECT_TYPE);
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
}
