<?php

namespace App\Form\Nph;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class NphSampleFinalize extends NphOrderForm
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $sample = $options['sample'];
        $orderType = $options['orderType'];

        $this->addCollectedTimeAndNoteFields($builder, $options, $sample);

        if ($orderType === 'urine') {
            $this->addUrineMetadataFields($builder);
        }

        if ($orderType === 'stool') {
            $this->addStoolMetadataFields($builder);
        }

        foreach ($options['aliquotIdentifiers'] as $aliquotCode => $aliquot) {
            for($i = 0; $i < $aliquot['expectedAliquots']; $i++) {
                $builder->add("{$aliquotCode}_{$i}", Type\TextType::class, [
                    'label' => $aliquot['container'],
                    'required' => false,
                    'constraints' => new Constraints\Type('string'),
                    'attr' => [
                        'placeholder' => 'Scan Aliquot Barcode'
                    ]
                ]);

                $builder->add("{$aliquotCode}AliquotTs_{$i}", Type\DateTimeType::class, [
                    'required' => false,
                    'label' => 'Aliquot Time',
                    'widget' => 'single_text',
                    'format' => 'M/d/yyyy h:mm a',
                    'html5' => false,
                    'model_timezone' => 'UTC',
                    'view_timezone' => $options['timeZone'],
                    'constraints' => [
                        new Constraints\Type('datetime'),
                        new Constraints\LessThanOrEqual([
                            'value' => new \DateTime('+5 minutes'),
                            'message' => 'Date cannot be in the future'
                        ])
                    ],
                    'attr' => [
                        'class' => 'sample-aliquot-ts',
                    ]
                ]);

                $builder->add("{$aliquotCode}Volume_{$i}", Type\TextType::class, [
                    'label' => 'Volume',
                    'required' => false,
                    'constraints' => new Constraints\Type('string')
                ]);
            }
        }

        return $builder->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'sample' => null,
            'orderType' => null,
            'timeZone' => null,
            'aliquotIdentifiers' => null
        ]);
    }
}
