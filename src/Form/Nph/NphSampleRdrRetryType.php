<?php

namespace App\Form\Nph;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;

class NphSampleRdrRetryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('retry', Type\SubmitType::class, [
            'label' => 'Retry',
            'attr' => [
                'class' => 'btn-success btn-sm'
            ]
        ]);
    }
}
