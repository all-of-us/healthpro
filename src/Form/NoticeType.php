<?php

namespace App\Form;

use App\Entity\Notice;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\CallbackTransformer;

class NoticeType extends AbstractType
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
                'attr' => ['rows' => 4],
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ]
            ])
            ->add('full_page', Type\ChoiceType::class, [
                'label' => 'Take Page/Application Down?',
                'required' => true,
                'choices' => [
                    'No'=> 0,
                    'Yes' => 1
                ],
                'constraints' => [
                    new Constraints\Callback(function ($isFullPage, $context) {
                        if ($isFullPage) {
                            $url = $context->getObject()->getParent()->get('url')->getData();
                            if (preg_match('/^\/?admin/i', $url)) {
                                $context->buildViolation('Full page notices cannot be used with admin URLs')->addViolation();
                            }
                        }
                    })
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
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Notice::class,
            'timezone' => 'UTC',
        ]);
    }
}
