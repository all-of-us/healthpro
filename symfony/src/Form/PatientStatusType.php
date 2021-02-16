<?php
namespace App\Form;

use App\Entity\PatientStatus;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PatientStatusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('status', Type\ChoiceType::class, [
                'label' => 'Is this participant a patient here?',
                'required' => true,
                'choices' => PatientStatus::$patientStatus,
                'placeholder' => '-- Select patient status --',
                'multiple' => false,
                'constraints' => new Constraints\NotBlank([
                    'message' => 'Please select patient status'
                ])
            ])
            ->add("comments", Type\TextareaType::class, [
                'label' => 'Comments',
                'required' => $options['require_comment'],
                'constraints' => new Constraints\Type('string')
            ]);
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'require_comment' => false
        ]);
    }
}
