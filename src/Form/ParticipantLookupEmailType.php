<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Constraints;

class ParticipantLookupEmailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', Type\EmailType::class, [
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => 'janedoe@example.com'
                ]
            ])
        ;
    }
}
