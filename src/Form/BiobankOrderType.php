<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BiobankOrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $disabled = $options['order']->isFormDisabled();
        $samples = $options['order']->getCustomRequestedSamples();
        if (!empty($samples)) {
            $samplesDisabled = $disabled;
            $builder->add("finalizedSamples", Type\ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => 'Which samples are being shipped to the All of Us℠ Biobank?',
                'choices' => $samples,
                'required' => false,
                'disabled' => $samplesDisabled
            ]);
        }
        if ($options['order']->getType() === 'kit' && empty($options['siteCentrifugeType'])) {
            $builder->add('processedCentrifugeType', Type\ChoiceType::class, [
                'label' => 'Centrifuge type',
                'required' => false,
                'choices' => [
                    '-- Select centrifuge type --' => null,
                    'Fixed Angle' => $options['order']::FIXED_ANGLE,
                    'Swinging Bucket' => $options['order']::SWINGING_BUCKET
                ],
                'multiple' => false
            ]);
        }
        $builder->add("finalizedNotes", Type\TextareaType::class, [
            'label' => 'Additional notes on finalization',
            'disabled' => $disabled,
            'required' => false,
            'constraints' => new Constraints\Type('string')
        ]);
        $form = $builder->getForm();
        return $form;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'order' => null,
            'siteCentrifugeType' => null
        ]);
    }
}
