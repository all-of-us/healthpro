<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints;

class GroupMemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', Type\TextType::class, [
                'label' => 'Pmi-Ops Credentials',
                'required' => true,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ]
            ])
            ->add('role', Type\TextType::class, [
                'label' => 'Role',
                'required' => false,
                'attr' => [
                    'value' => 'MEMBER',
                    'disabled' => true
                ]
            ]);
    }
}
