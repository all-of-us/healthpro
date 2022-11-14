<?php

namespace App\Form\Nph;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class NphOrderCollectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $samples = $options['samples'];
        $orderType = $options['orderType'];
        foreach ($samples as $sample => $sampleLabel) {
            $builder->add($orderType . $sample, Type\ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => $sampleLabel,
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
                ]
            ]);
            $builder->add("{$sample}Notes", Type\TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'constraints' => new Constraints\Type('string')
            ]);
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
