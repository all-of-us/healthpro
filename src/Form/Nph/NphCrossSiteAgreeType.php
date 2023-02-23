<?php

namespace App\Form\Nph;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;

class NphCrossSiteAgreeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('Acknowledge', Type\SubmitType::class, [
                'attr' => ['class' => 'btn btn-success'],
            ]);
    }
}
