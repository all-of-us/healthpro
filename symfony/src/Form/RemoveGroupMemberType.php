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
            ])
            ->add('memberLastDay', Type\DateType::class, [
                'widget' => 'single_text',
                'label' => "Please select the member's last day",
                'required' => false,
                'html5' => false,
                'format' => 'MM/dd/yyyy',
                'constraints' => [
                    new Constraints\DateTime(),
                    new Constraints\LessThanOrEqual([
                        'value' => new \DateTime('today'),
                        'message' => 'Date cannot be in the future'
                    ])
                ]
            ])
            ->add('reason', Type\ChoiceType::class, [
                'label' => 'Please provide the reason for removal',
                'required' => false,
                'expanded' => true,
                'multiple' => false,
                'placeholder' => false,
                'choices' => [
                    'Staff member no longer supports the All of Us program or has left the institution' => 'no',
                    'Staff member still supports the All of Us program but not this specific site' => 'yes'
                ]
            ]);
    }
}
