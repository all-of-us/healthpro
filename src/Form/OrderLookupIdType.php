<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class OrderLookupIdType extends AbstractType
{
    public const NPH_LOOKUP_TYPE = 'NPH';
    public const KIT_ID_PREFIX = 'KIT-';

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('orderId', Type\TextType::class, [
                'label' => 'Order ID',
                'attr' => ['placeholder' => 'Scan barcode or enter order ID'],
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ]
            ]);
        if ($options['lookupType'] === self::NPH_LOOKUP_TYPE) {
            $builder
                ->add('checkKitId', Type\CheckboxType::class, [
                    'label' => 'Check for KIT-ID',
                    'required' => false
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'lookupType' => null
        ]);
    }
}
