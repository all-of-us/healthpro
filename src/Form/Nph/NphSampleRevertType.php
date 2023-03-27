<?php

namespace App\Form\Nph;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;

class NphSampleRevertType extends AbstractType
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
