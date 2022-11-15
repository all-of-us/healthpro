<?php

namespace App\Form\Nph;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class NphOrderCollectType extends AbstractType
{
    public static $urineColors = [
        'Color 1' => 1,
        'Color 2' => 2,
        'Color 3' => 3,
        'Color 4' => 4,
        'Color 5' => 5,
        'Color 6' => 6,
        'Color 7' => 7,
        'Color 8' => 8,
    ];

    public static $urineClarity = [
        'Clean' => 'clean',
        'Slightly Cloudy' => 'slightly_cloudy',
        'Cloudy' => 'cloudy',
        'Turbid' => 'turbid'
    ];

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $samples = $options['samples'];
        $orderType = $options['orderType'];
        foreach ($samples as $sample => $sampleLabel) {
            $builder->add($sample, Type\ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => 'Sample',
                'choices' => [$sampleLabel => $sample],
                'required' => false
            ]);
            $constraintDateTime = new \DateTime('+5 minutes'); // add buffer for time skew
            $builder->add("{$sample}CollectionTs", Type\DateTimeType::class, [
                'required' => false,
                'label' => 'Collection Time',
                'widget' => 'single_text',
                'format' => 'M/d/yyyy h:mm a',
                'html5' => false,
                'model_timezone' => $options['timeZone'],
                'view_timezone' => $options['timeZone'],
                'constraints' => [
                    new Constraints\Type('datetime'),
                    new Constraints\LessThanOrEqual([
                        'value' => $constraintDateTime,
                        'message' => 'Date cannot be in the future'
                    ])
                ],
                'attr' => [
                    'class' => 'order-collection-ts',
                ]
            ]);
            $builder->add("{$sample}Notes", Type\TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'constraints' => new Constraints\Type('string')
            ]);

            if ($orderType === 'urine') {
                $builder->add('urineColor', Type\ChoiceType::class, [
                    'label' => 'Urine Color',
                    'required' => false,
                    'choices' => self::$urineColors,
                    'multiple' => false,
                    'placeholder' => 'Select Urine Color'
                ]);

                $builder->add('urineClarity', Type\ChoiceType::class, [
                    'label' => 'Urine Clarity',
                    'required' => false,
                    'choices' => self::$urineClarity,
                    'multiple' => false,
                    'placeholder' => 'Select Urine Clarity'
                ]);
            }
        }
        return $builder->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'samples' => null,
            'orderType' => null,
            'timeZone' => null
        ]);
    }
}
