<?php

namespace App\Form;

use App\Entity\Dlw;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DlwType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('doseBatchId')
            ->add('actualDose')
            ->add('participantWeight')
            ->add('calculatedDosage', null, ['mapped' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dlw::class,
        ]);
    }
}
