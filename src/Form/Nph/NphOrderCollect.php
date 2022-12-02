<?php

namespace App\Form\Nph;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NphOrderCollect extends NphOrderForm
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $samples = $options['samples'];
        $orderType = $options['orderType'];
        foreach ($samples as $sample => $sampleLabel) {
            $this->addCollectedSampleFields($builder, $sample, $sampleLabel);
            $this->addCollectedTimeAndNoteFields($builder, $options, $sample);
        }

        if ($orderType === 'urine') {
            $this->addUrineMetadataFields($builder);
        }

        if ($orderType === 'stool') {
            $this->addStoolMetadataFields($builder);
        }
        return $builder->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'samples' => null,
            'orderType' => null,
            'timeZone' => null
        ]);
    }
}
