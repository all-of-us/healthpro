<?php

namespace App\Form;

use App\Entity\WorkqueueView;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Form\FormBuilderInterface;

class WorkQueueViewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', Type\TextType::class, [
                'label' => 'View Name',
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ]
            ])
            ->add('defaultView', Type\CheckboxType::class, [
                'label' => 'Set as default',
                'required' => false
            ])
            ->add('type', Type\HiddenType::class, [
                'data' => $options['type'] ?? $builder->getData()->getType()
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => WorkqueueView::class,
            'type' => null
        ]);
    }
}
