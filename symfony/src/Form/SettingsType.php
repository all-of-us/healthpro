<?php
namespace App\Form;

use App\Entity\User;
use App\Service\TimezoneService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\CallbackTransformer;

class SettingsType extends AbstractType
{
    protected $timezoneService;

    public function __construct(TimezoneService $timezoneService)
    {
        $this->timezoneService = $timezoneService;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('timezone', Type\ChoiceType::class, [
                'label' => 'Time zone',
                'choices' => array_flip($this->timezoneService::$timezoneOptions),
                'placeholder' => '-- Select your time zone --',
                'constraints' => new Constraints\NotBlank()
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
