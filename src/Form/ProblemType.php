<?php

namespace App\Form;

use App\Entity\Problem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as Type;
use Symfony\Component\Validator\Constraints as Constraints;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProblemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $constraintDateTime = new \DateTime('+5 minutes');
        $problemTypeAttributes = [
            'label' => 'Unanticipated problem type',
            'required' => true,
            'disabled' => $options['formDisabled'],
            'choices' => [
                Problem::PROBLEM_TYPE_OPTIONS[0]=> Problem::RELATED_BASELINE,
                Problem::PROBLEM_TYPE_OPTIONS[1] => Problem::UNRELATED_BASELINE,
                Problem::PROBLEM_TYPE_OPTIONS[2] => Problem::OTHER
            ],
            'multiple' => false,
            'expanded' => true
        ];
        $siteAttributes = [
            'label' => 'Name of enrollment site where provider became aware of problem',
            'required' => false,
            'disabled' => $options['formDisabled'],
            'constraints' => [
                new Constraints\Type('string')
            ]
        ];
        $staffAttributes = [
            'label' => 'Name of staff recording the problem',
            'required' => false,
            'disabled' => $options['formDisabled'],
            'constraints' => [
                new Constraints\Type('string')
            ]
        ];
        $problemDateAttributes = [
            'label' => 'Date of problem',
            'widget' => 'single_text',
            'format' => 'M/d/yyyy h:mm a',
            'html5' => false,
            'required' => false,
            'disabled' => $options['formDisabled'],
            'view_timezone' => $options['user']->getTimezone(),
            'model_timezone' => 'UTC',
            'constraints' => [
                new Constraints\LessThanOrEqual([
                    'value' => $constraintDateTime,
                    'message' => 'Timestamp cannot be in the future'
                ])
            ]
        ];
        $providerAwareDateAttributes = [
            'label' => 'Date provider became aware of problem',
            'widget' => 'single_text',
            'format' => 'M/d/yyyy h:mm a',
            'html5' => false,
            'required' => false,
            'disabled' => $options['formDisabled'],
            'view_timezone' => $options['user']->getTimezone(),
            'model_timezone' => 'UTC',
            'constraints' => [
                new Constraints\LessThanOrEqual([
                    'value' => $constraintDateTime,
                    'message' => 'Timestamp cannot be in the future'
                ])
            ]
        ];
        $descriptionAttributes = [
            'label' => 'Description of problem',
            'required' => false,
            'disabled' => $options['formDisabled'],
            'constraints' => [
                new Constraints\Type('string')
            ]
        ];
        $actionTakenAttributes = [
            'label' => 'Description of corrective action taken',
            'required' => false,
            'disabled' => $options['formDisabled'],
            'constraints' => [
                new Constraints\Type('string')
            ]
        ];
        if ($options['enableConstraints']) {
            $siteAttributes['constraints'][] = new Constraints\NotBlank();
            $staffAttributes['constraints'][] = new Constraints\NotBlank();
            $problemDateAttributes['constraints'][] = new Constraints\NotBlank();
            $providerAwareDateAttributes['constraints'][] = new Constraints\NotBlank();
            $descriptionAttributes['constraints'][] = new Constraints\NotBlank();
            $actionTakenAttributes['constraints'][] = new Constraints\NotBlank();
        }

        $builder
            ->add('problem_type', Type\ChoiceType::class, $problemTypeAttributes)
            ->add('enrollment_site', Type\TextType::class, $siteAttributes)
            ->add('staff_name', Type\TextType::class, $staffAttributes)
            ->add('problem_date', Type\DateTimeType::class, $problemDateAttributes)
            ->add('provider_aware_date', Type\DateTimeType::class, $providerAwareDateAttributes)
            ->add('description', Type\TextareaType::class, $descriptionAttributes)
            ->add('action_taken', Type\TextareaType::class, $actionTakenAttributes)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Problem::class,
            'enableConstraints' => false,
            'formDisabled' => false,
            'user' => new \App\Entity\User()
        ]);
    }
}
