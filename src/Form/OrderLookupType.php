<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Form\FormBuilderInterface;

class OrderLookupType extends AbstractType
{
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
    }
}
