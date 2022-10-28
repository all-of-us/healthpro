<?php

namespace App\Form\Nph;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NphOrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $timePointSamples = $options['timePointSamples'];
        foreach ($timePointSamples as $timePoint => $samples) {
            $builder->add($timePoint, Type\ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => $timePoint,
                'choices' => array_flip($samples),
                'required' => false
            ]);
        }
        return $builder->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'timePointSamples' => null
        ]);
    }
}
