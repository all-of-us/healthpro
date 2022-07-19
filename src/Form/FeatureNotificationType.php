<?php

namespace App\Form;

use App\Entity\FeatureNotification;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\CallbackTransformer;

class FeatureNotificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('status', Type\CheckboxType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'data-toggle' => 'toggle',
                    'data-on' => 'Enable',
                    'data-off' => 'Disable',
                    'data-onstyle' => 'success'
                ]
            ])
            ->add('title', Type\TextType::class, [
                'label' => 'Title',
                'required' => true,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ]
            ])
            ->add('message', Type\TextareaType::class, [
                'required' => true,
                'attr' => ['rows' => 4],
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ]
            ])
            ->add('new_feature_status', Type\CheckboxType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'data-toggle' => 'toggle',
                    'data-on' => 'Enable',
                    'data-off' => 'Disable',
                    'data-onstyle' => 'success'
                ]
            ])
            ->add('url', Type\TextType::class, [
                'label' => 'Direct User To (URL Pattern)',
                'required' => true,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string'),
                    new Constraints\Regex('/^[a-zA-Z0-9_\-\/\*]+$/') // valid URL, with asterisks
                ]
            ])
            ->add('start_ts', Type\DateTimeType::class, [
                'required' => false,
                'label' => 'Start Time (optional)',
                'widget' => 'single_text',
                'format' => 'M/d/yyyy h:mm a',
                'html5' => false,
                'view_timezone' => $options['timezone'],
                'model_timezone' => 'UTC',
                'constraints' => [
                    new Constraints\Type('datetime')
                ]
            ])
            ->add('end_ts', Type\DateTimeType::class, [
                'required' => false,
                'label' => 'End Time (optional)',
                'widget' => 'single_text',
                'format' => 'M/d/yyyy h:mm a',
                'html5' => false,
                'view_timezone' => $options['timezone'],
                'model_timezone' => 'UTC',
                'constraints' => [
                    new Constraints\Type('datetime')
                ]
            ]);

        // Convert status field int values into boolean
        $builder->get('status')
            ->addModelTransformer(new CallbackTransformer(
                function ($int) {
                    return (bool)$int;
                },
                function ($bool) {
                    return $bool ? 1 : 0;
                }
            ));

        $builder->get('new_feature_status')
            ->addModelTransformer(new CallbackTransformer(
                function ($int) {
                    return (bool)$int;
                },
                function ($bool) {
                    return $bool ? 1 : 0;
                }
            ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => FeatureNotification::class,
            'timezone' => 'UTC',
        ]);
    }
}
