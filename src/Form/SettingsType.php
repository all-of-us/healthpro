<?php

namespace App\Form;

use App\Entity\User;
use App\Service\TimezoneService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

/**
 * @extends AbstractType<mixed>
 */
class SettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('timezone', Type\ChoiceType::class, [
                'label' => 'Time zone',
                'choices' => array_flip(TimezoneService::$timezoneOptions),
                'placeholder' => '-- Select your time zone --',
                'constraints' => new Constraints\NotBlank()
            ])
            ->add('clientTimezone', Type\HiddenType::class, [
                'mapped' => false
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class
        ]);
    }
}
