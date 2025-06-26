<?php

namespace App\Form\Nph;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class NphSampleLookupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('sampleId', Type\TextType::class, [
                'label' => $options['label'] ?? 'Scan or manually enter the collection sample ID',
                'attr' => [
                    'placeholder' => $options['placeholder'] ?? 'Scan barcode or enter collection sample ID',
                    'autofocus' => true
                ],
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'label' => null,
            'placeholder' => null
        ]);
    }
}
