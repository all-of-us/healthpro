<?php

namespace App\Form;

use App\Entity\Problem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as Type;
use Symfony\Component\Validator\Constraints as Constraints;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WorkQueueParticipantLookupSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('lastName', Type\TextType::class, [
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ]
            ])
            ->add('firstName', Type\TextType::class, [
                'constraints' => [
                    new Constraints\Type('string')
                ],
                'required' => false
            ])
            ->add('dateOfBirth', Type\TextType::class, [
                'label' => 'Date of birth',
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => 'MM/DD/YYYY'
                ]
            ])
        ;
    }

    public function getBlockPrefix()
    {
        return '';
    }
}
