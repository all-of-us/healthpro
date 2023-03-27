<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Constraints;

class ParticipantLookupTelephoneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('phone', Type\TelType::class, [
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => '(999) 999-9999',
                    'pattern' => '^\(?\d{3}\)? ?\d{3}-?\d{4}$',
                    'data-parsley-error-message' => 'This value should be a 10 digit phone number.'
                ]
            ])
        ;
    }
}
