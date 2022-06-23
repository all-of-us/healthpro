<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;

class IdVerificationImportFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('id_verification_csv', Type\FileType::class, [
                'label' => 'Upload CSV File',
                'required' => true,
                'constraints' => new File([
                    'maxSize' => '5M'
                ])
            ])
            ->add('Upload', Type\SubmitType::class, [
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }
}
