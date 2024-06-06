<?php

namespace App\Form\Nph;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;

class NphSampleProcessCompleteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('status', Type\HiddenType::class);
        $builder->add('period', Type\HiddenType::class);
        $builder->add('module', Type\HiddenType::class);
        $builder->add('modifyType', Type\HiddenType::class);
    }
}
