<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParticipantLookupBiobankIdType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $constraints = [
            new Constraints\NotBlank(),
            new Constraints\Type('string')
        ];
        if (!empty($options['bioBankIdPrefix'])) {
            $bioBankIdPrefixQuote = preg_quote($options['bioBankIdPrefix'], '/');
            $constraints[] = new Constraints\Regex([
                'pattern' => "/^{$bioBankIdPrefixQuote}\d+$/",
                'message' => "Invalid biobank ID. Must be in the format of {$options['bioBankIdPrefix']}000000000"
            ]);
        }
        $builder
            ->add('biobankId', Type\TextType::class, [
                'label' => 'Biobank ID',
                'constraints' => $constraints,
                'attr' => [
                    'placeholder' => !empty($options['bioBankIdPrefix']) ? $options['bioBankIdPrefix'] . '000000000' : 'Enter biobank ID'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'bioBankIdPrefix' => null
        ]);
    }
}
