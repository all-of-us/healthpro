<?php

namespace App\Form;

use App\Entity\DeceasedReport;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class DeceasedReportReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('reportStatus', ChoiceType::class, [
                'label' => 'Do you accept marking this participant as deceased?',
                'choices' => [
                    'Yes' => 'final',
                    'No' => 'cancelled',
                ],
                'expanded' => true
            ])
            ->add('denialReason', ChoiceType::class, [
                'label' => 'Reason for denial',
                'choices' => [
                    'Incorrect participant' => 'INCORRECT_PARTICIPANT',
                    'Marked in error' => 'MARKED_IN_ERROR',
                    'Insufficient information' => 'INSUFFICIENT_INFORMATION',
                    'Other' => 'OTHER',
                ],
                'placeholder' => '-- Select One --',
                'required' => false,
                'constraints' => [
                    new Constraints\NotBlank([
                        'groups' => ['cancel_group']
                    ])
                ]
            ])
            ->add('denialReasonOtherDescription', TextareaType::class, [
                'label' => 'Describe reason for denial',
                'required' => false,
                'constraints' => [
                    new Constraints\NotBlank([
                        'groups' => ['cancel_group_other']
                    ])
                ]
            ])
            ->add('reviewedBy', HiddenType::class, [
                'data' => $options['reviewer_email']
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Submit',
                'attr' => [
                    'class' => 'btn-primary'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => DeceasedReport::class,
            'attr' => ['data-parsley-validate' => true],
            'validation_groups' => function (FormInterface $form) {
                $groups = ['Default'];
                $data = $form->getData();
                if ($data->getReportStatus() === 'cancelled') {
                    $groups[] = 'cancel_group';
                }
                if ($data->getDenialReason() === 'OTHER') {
                    $groups[] = 'cancel_group_other';
                }
                return $groups;
            },
            'reviewer_email' => null
        ]);
    }
}
