<?php

namespace App\Form\Nph;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Form\FormBuilderInterface;

class NphSampleLookupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('sampleId', Type\TextType::class, [
                'label' => 'Sample ID',
                'attr' => ['placeholder' => 'Scan barcode or enter collection sample ID'],
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ]
            ]);
    }
}
