<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints;

class DebugParticipantLookupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('participantId', Type\TextType::class, [
                'label' => 'Participant ID',
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => 'P000000000'
                ]
            ])
            ->add('Go', Type\SubmitType::class, [
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }
}
