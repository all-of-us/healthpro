<?php

namespace App\Form\Nph;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class NphSampleFinalizeType extends NphOrderForm
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

        foreach ($options['aliquots'] as $aliquotCode => $aliquot) {
            $data = [];
            for ($i = 0; $i < $aliquot['expectedAliquots']; $i++) {
                $data[] = null;
            }

            $builder->add("{$aliquotCode}", Type\CollectionType::class, [
                'entry_type' => Type\TextType::class,
                'entry_options' => [
                    'attr' => [
                        'placeholder' => 'Scan Aliquot Barcode'
                    ],
                ],
                'label' => $aliquot['container'],
                'required' => false,
                'constraints' => new Constraints\Type('string'),
                'allow_add' => true,
                'data' => $data,
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
                        ])
                    ],
                    'attr' => [
                        'class' => 'order-ts',
                    ]
                ],
                'required' => false,
                'data' => $data,
            ]);

            $builder->add("{$aliquotCode}Volume", Type\CollectionType::class, [
                'entry_type' => Type\TextType::class,
                'label' => 'Volume',
                'required' => false,
                'allow_add' => true,
                'data' => $data,
            ]);
        }

        return $builder->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'sample' => null,
            'orderType' => null,
            'timeZone' => null,
            'aliquots' => null
        ]);
    }
}
