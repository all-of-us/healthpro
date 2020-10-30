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

class MissingEvaluationsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('ids', Type\ChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'choices' => $options['choices'],
                'choice_label' => false
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'choices' => []
        ]);
    }
}
