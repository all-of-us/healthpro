<?php

namespace App\Form\Nph;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;

class NphSampleProcessCompleteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('sampleProcessComplete', Type\HiddenType::class);
        $builder->add('visitType', Type\HiddenType::class);
        $builder->add('moduleNumber', Type\HiddenType::class);
    }
}
