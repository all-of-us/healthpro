<?php

namespace App\Form;

use App\Entity\Problem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as Type;
use Symfony\Component\Validator\Constraints as Constraints;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
                    'class' => 'loginPhone',
                    'pattern' => '^\(?\d{3}\)? ?\d{3}-?\d{4}$',
                    'data-parsley-error-message' => 'This value should be a 10 digit phone number.'
                ]
            ])
        ;
    }
}
