<?php

namespace App\Form\Nph;

use App\Nph\Order\Samples;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class NphOrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $timePointSamples = $options['timePointSamples'];
        $timePoints = $options['timePoints'];
        foreach ($timePointSamples as $timePoint => $samples) {
            foreach ($samples as $sampleCode => $sample) {
                if ($sampleCode === 'ST1') {
                    $builder->add('stoolKit', Type\TextType::class, [
                        'label' => 'Stool Kit ID',
                        'required' => false,
                        'constraints' => new Constraints\Type('string'),
                        'attr' => [
                            'placeholder' => 'Scan Kit ID'
                        ]
                    ]);
                }
                if (in_array($sampleCode, Samples::$stoolSamples)) {
                    $builder->add($sampleCode, Type\TextType::class, [
                        'label' => $sample,
                        'required' => false,
                        'constraints' => new Constraints\Type('string'),
                        'attr' => [
                            'placeholder' => 'Scan Tube'
                        ]
                    ]);
                    unset($samples[$sampleCode]);
                }
            }
            $builder->add($timePoint, Type\ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => $timePoints[$timePoint],
                'choices' => array_flip($samples),
                'required' => false
            ]);
        }
        return $builder->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'timePointSamples' => null,
            'timePoints' => null
        ]);
    }
}
