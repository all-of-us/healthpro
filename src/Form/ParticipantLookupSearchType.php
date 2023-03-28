<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Constraints;

class ParticipantLookupSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('lastName', Type\TextType::class, [
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => 'Doe'
                ]
            ])
            ->add('firstName', Type\TextType::class, [
                'constraints' => [
                    new Constraints\Type('string')
                ],
                'required' => false,
                'attr' => [
                    'placeholder' => 'John'
                ]
            ])
            ->add('dob', Type\TextType::class, [
                'label' => 'Date of birth',
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => '11/1/1980'
                ]
            ])
        ;
    }
}
