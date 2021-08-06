<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;

class RemoveGroupMemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('confirm', Type\ChoiceType::class, [
                'label' => 'Are you sure you want to remove this member?',
                'required' => true,
                'expanded' => true,
                'multiple' => false,
                'choices' => [
                    'Yes' => 'yes',
                    'No' => 'no'
                ],
                'constraints' => [
                    new Constraints\NotBlank()
                ]
            ]);
    }
}
