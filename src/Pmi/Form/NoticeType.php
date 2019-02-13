<?php
namespace Pmi\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NoticeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('url', Type\TextType::class, [
                'label' => 'URL Pattern',
                'required' => true,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string'),
                    new Constraints\Regex('/^[a-zA-Z0-9_\-\/\*]+$/') // valid URL, with asterisks
                ]
            ])
            ->add('message', Type\TextareaType::class, [
                'required' => true,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ]
            ])
            ->add('full_page', Type\ChoiceType::class, [
                'label' => 'Full Page?',
                'required' => true,
                'choices' => [
                    'No'=> 0,
                    'Yes' => 1
                ]
            ])
            ->add('start_ts', Type\DateTimeType::class, [
                'required' => false,
                'label' => 'Start Time (optional)',
                'widget' => 'single_text',
                'format' => 'M/d/yyyy h:mm a',
                'view_timezone' => $options['timezone'],
                'model_timezone' => 'UTC',
                'constraints' => [
                    new Constraints\DateTime()
                ]
            ])
            ->add('end_ts', Type\DateTimeType::class, [
                'required' => false,
                'label' => 'End Time (optional)',
                'widget' => 'single_text',
                'format' => 'M/d/yyyy h:mm a',
                'view_timezone' => $options['timezone'],
                'model_timezone' => 'UTC',
                'constraints' => [
                    new Constraints\DateTime()
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'timezone' => 'UTC',
        ]);
    }
}