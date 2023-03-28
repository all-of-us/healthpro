<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints;

class WorkQueueViewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', Type\TextType::class, [
                'label' => 'View Name',
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string'),
                    new Constraints\Length(['max' => 50])
                ],
                'attr' => [
                    'data-parsley-maxlength' => 50
                ]
            ]);
    }
}
