<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;

class CrossOriginAgreeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('Acknowledge', Type\SubmitType::class, [
                'attr' => ['class' => 'btn btn-success'],
            ])
        ;
    }
}
