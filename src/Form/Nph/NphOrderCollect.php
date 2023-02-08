<?php

namespace App\Form\Nph;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
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
                    new Constraints\Callback(function ($value, $context) use ($sampleCode) {
                        if ($value === false && !empty($context->getRoot()["{$sampleCode}CollectedTs"]->getData())) {
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

        if ($orderType === 'urine') {
            $this->addUrineMetadataFields($builder, $options['disableMetadataFields']);
        }

        if ($orderType === 'stool') {
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
            'disableMetadataFields' => null
        ]);
    }
}
