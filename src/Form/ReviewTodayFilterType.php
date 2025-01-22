<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class ReviewTodayFilterType extends AbstractType
{
    public const DATE_RANGE_LIMIT = 30;

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $constraintDate = new \DateTime('today', new \DateTimeZone($options['timezone']));
        return $builder
            ->add('start_date', Type\DateTimeType::class, [
                'required' => true,
                'label' => 'Start Date',
                'widget' => 'single_text',
                'format' => 'MM/dd/yyyy',
                'html5' => false,
                'model_timezone' => $options['timezone'],
                'view_timezone' => $options['timezone'],
                'constraints' => [
                    new Constraints\Type('datetime'),
                    new Constraints\LessThanOrEqual([
                        'value' => $constraintDate,
                        'message' => 'Date cannot be in the future'
                    ])
                ]
            ])
            ->add('end_date', Type\DateTimeType::class, [
                'required' => false,
                'label' => 'End Date',
                'widget' => 'single_text',
                'format' => 'MM/dd/yyyy',
                'html5' => false,
                'model_timezone' => $options['timezone'],
                'view_timezone' => $options['timezone'],
                'constraints' => [
                    new Constraints\Type('datetime'),
                    new Constraints\GreaterThanOrEqual([
                        'propertyPath' => 'parent.all[start_date].data',
                        'message' => 'End date should be greater than start date'
                    ]),
                    new Constraints\LessThanOrEqual([
                        'value' => $constraintDate,
                        'message' => 'Date cannot be in the future'
                    ])
                ]
            ])
            ->add('Submit', Type\SubmitType::class, [
                'label' => 'Submit <span class="spinner-border spinner-border-sm" style="display: none;"></span>',
                'label_html' => true,
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'timezone' => null,
            'unmapped' => true
        ]);
    }
}
