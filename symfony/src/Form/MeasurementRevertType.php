<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;

class MeasurementRevertType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('revert', Type\SubmitType::class, [
            'label' => 'Revert',
            'attr' => [
                'class' => 'btn-warning'
            ]
        ]);
    }
}
