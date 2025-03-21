<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;

class OrderRevertType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('revert', Type\SubmitType::class, [
            'label' => 'Revert <span class="spinner-border spinner-border-sm-bs5" style="display: none;"></span>',
            'label_html' => true,
            'attr' => [
                'class' => 'btn-warning'
            ]
        ]);
    }
}
