<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;

class PatientStatusImportConfirmFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('Confirm', Type\SubmitType::class, [
                'attr' => ['class' => 'btn btn-primary'],
            ])
            ->add('Cancel', Type\SubmitType::class, [
                'attr' => ['class' => 'btn btn-danger'],
            ]);
    }
}
