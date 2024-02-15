<?php

namespace App\Form;

use App\Entity\DeceasedReport;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class DeceasedReportType extends AbstractType
{
    private const AOU_START_DATE = '2018-05-06';

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('dateOfDeath', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date of Death (if offered)',
                'required' => false,
                'html5' => false,
                'format' => 'MM/dd/yyyy',
                'help' => 'Do NOT prompt report for this information and only enter if offered.',
                'constraints' => [
                    new Constraints\Type('datetime'),
                    new Constraints\LessThanOrEqual([
                        'value' => new \DateTime('today'),
                        'message' => 'Date cannot be in the future'
                    ]),
                    new Constraints\GreaterThan([
                        'value' => $options['dob'],
                        'message' => 'Date should be greater than participant\'s date of birth'
                    ]),
                    new Constraints\GreaterThanOrEqual([
                        'value' => new \DateTime(self::AOU_START_DATE),
                        'message' => 'Date should be greater than or equal to AOU start date'
                    ]),
                ],
                'attr' => [
                    'autocomplete' => 'off',
                    'placeholder' => 'MM/DD/YYYY'
                ]
            ])
            ->add('causeOfDeath', TextType::class, [
                'label' => 'Cause of Death (if offered)',
                'required' => false,
                'help' => 'Please do not enter PII or the participant’s PMID when describing the cause of death. Do NOT prompt reporter for this information and only enter if offered.'
            ])
            ->add('reportMechanism', ChoiceType::class, [
                'label' => 'Notification Mechanism',
                'choices' => [
                    'Electronic Health Record (EHR)' => 'EHR',
                    'Attempted to contact participant' => 'ATTEMPTED_CONTACT',
                    'Next of kin contacted HPO' => 'NEXT_KIN_HPO',
                    'Next of kin contacted Support Center' => 'NEXT_KIN_SUPPORT',
                    'Other' => 'OTHER'
                ],
                'expanded' => true,
                'required' => true
            ])
            ->add('nextOfKinName', TextType::class, [
                'label' => 'Next of kin\'s full name',
                'required' => false
            ])
            ->add('nextOfKinRelationship', ChoiceType::class, [
                'label' => 'Next of kin\'s relationship to participant',
                'choices' => [
                    'Parent' => 'PRN',
                    'Child' => 'CHILD',
                    'Sibling' => 'SIB',
                    'Spouse' => 'SPS',
                    'Other' => 'O'
                ],
                'placeholder' => '-- Select One --',
                'required' => false
            ])
            ->add('nextOfKinTelephoneNumber', TextType::class, [
                'label' => 'Next of kin\'s phone number (optional)',
                'attr' => [
                    'placeholder' => '(555) 555-5555'
                ],
                'help' => 'Only collect if next of kin would like to receive confirmation of deceased reporting.',
                'required' => false
            ])
            ->add('nextOfKinEmail', TextType::class, [
                'label' => 'Next of kin\'s e-mail address (optional)',
                'attr' => [
                    'placeholder' => 'user@example.com'
                ],
                'help' => 'Only collect if next of kin would like to receive confirmation of deceased reporting.',
                'required' => false
            ])
            ->add('reportMechanismOtherDescription', TextareaType::class, [
                'label' => 'Please describe notification mechanism',
                'required' => false
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
            'dob' => null
        ]);
    }
}
