<?php

namespace App\Form\Nph;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class NphSampleFinalizeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $sample = $options['sample'];
        $orderType = $options['orderType'];
        $constraintDateTime = new \DateTime('+5 minutes'); // add buffer for time skew
        $builder->add("{$sample}CollectedTs", Type\DateTimeType::class, [
            'required' => false,
            'label' => 'Collection Time',
            'widget' => 'single_text',
            'format' => 'M/d/yyyy h:mm a',
            'html5' => false,
            'model_timezone' => 'UTC',
            'view_timezone' => $options['timeZone'],
            'constraints' => [
                new Constraints\Type('datetime'),
                new Constraints\LessThanOrEqual([
                    'value' => $constraintDateTime,
                    'message' => 'Date cannot be in the future'
                ])
            ],
            'attr' => [
                'class' => 'order-collected-ts',
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
                'choices' => NphOrderCollectType::$urineColors,
                'multiple' => false,
                'placeholder' => 'Select Urine Color'
            ]);

            $builder->add('urineClarity', Type\ChoiceType::class, [
                'label' => 'Urine Clarity',
                'required' => false,
                'choices' => NphOrderCollectType::$urineClarity,
                'multiple' => false,
                'placeholder' => 'Select Urine Clarity'
            ]);
        }

        if ($orderType === 'stool') {
            $builder->add('bowelType', Type\ChoiceType::class, [
                'label' => 'Describe the bowel movement for this collection',
                'required' => false,
                'choices' => NphOrderCollectType::$bowelMovements,
                'multiple' => false,
                'placeholder' => 'Select bowel movement type'
            ]);

            $builder->add('bowelQuality', Type\ChoiceType::class, [
                'label' => 'Describe the typical quality of your bowel movements',
                'required' => false,
                'choices' => NphOrderCollectType::$bowelMovementQuality,
                'multiple' => false,
                'placeholder' => 'Select bowel movement quality'
            ]);
        }
        return $builder->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'sample' => null,
            'orderType' => null,
            'timeZone' => null
        ]);
    }
}
