<?php
namespace App\Form;

use App\Entity\Site;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Validation;

class SiteType extends AbstractType
{
    const FIXED_ANGLE = 'fixed_angle';
    const SWINGING_BUCKET = 'swinging_bucket';
    const FULL_DATA_ACCESS = 'full_data';
    const LIMITED_DATA_ACCESS = 'limited_data';
    const DOWNLOAD_DISABLED = 'disabled';


    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', Type\TextType::class, [
                'label' => 'Name',
                'required' => true,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'disabled' => $options['isDisabled'],
            ])
            ->add('status', Type\ChoiceType::class, [
                'label' => 'Status',
                'required' => true,
                'choices' => [
                    'Active'=> 1,
                    'Inactive' => 0
                ],
                'disabled' => $options['isDisabled']
            ])
            ->add('google_group', Type\TextType::class, [
                'label' => 'Google Group',
                'required' => true,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'disabled' => $options['isDisabled'],
            ])
            ->add('organization', Type\TextType::class, [
                'label' => 'Awardee (formerly HPO ID)',
                'required' => false,
                'constraints' => new Constraints\Type('string'),
                'disabled' => $options['isDisabled'],
            ])
            ->add('organization_id', Type\TextType::class, [
                'label' => 'Organization',
                'required' => false,
                'constraints' => new Constraints\Type('string'),
                'disabled' => $options['isDisabled'],
            ])
            ->add('mayolink_account', Type\TextType::class, [
                'label' => 'MayoLINK Account',
                'required' => false,
                'constraints' => new Constraints\Type('string'),
                'disabled' => $options['isDisabled'] && $options['isProd'],
            ])
            ->add('type', Type\TextType::class, [
                'label' => 'Type (e.g. HPO, DV)',
                'required' => false,
                'constraints' => new Constraints\Type('string'),
                'disabled' => $options['isDisabled'],
            ])
            ->add('site_type', Type\TextType::class, [
                'label' => 'Site Type',
                'required' => false,
                'constraints' => new Constraints\Type('string'),
                'disabled' => $options['isDisabled'],
            ])
            ->add('email', Type\TextType::class, [
                'label' => 'Email address(es)',
                'required' => false,
                'constraints' => [
                    new Constraints\Type('string'),
                    new Constraints\Length(['max' => 512]),
                    new Constraints\Callback(function($list, $context) {
                        $list = trim($list);
                        if (empty($list)) {
                            return;
                        }
                        $emails = explode(',', $list);
                        $validator = Validation::createValidator();
                        foreach ($emails as $email) {
                            $email = trim($email);
                            $errors = $validator->validate($email, new Constraints\Email());
                            if (count($errors) > 0) {
                                $context
                                    ->buildViolation('Must be a comma-separated list of valid email addresses')
                                    ->addViolation();
                                break;
                            }
                        }
                    })
                ],
                'disabled' => $options['isDisabled'] && $options['isProd'],
            ])
            ->add('awardee', Type\TextType::class, [
                'label' => 'Program (e.g. STSI)',
                'required' => false,
                'constraints' => new Constraints\Type('string')
            ])
            ->add('centrifuge_type', Type\ChoiceType::class, [
                'label' => 'Centrifuge type',
                'required' => false,
                'choices' => [
                    '-- Select centrifuge type --' => null,
                    'Fixed Angle'=> self::FIXED_ANGLE,
                    'Swinging Bucket' => self::SWINGING_BUCKET
                ],
                'multiple' => false
            ])
            ->add('workqueue_download', Type\ChoiceType::class, [
                'label' => 'Work Queue Download',
                'required' => true,
                'choices' => [
                    'Full Data Access'=> self::FULL_DATA_ACCESS,
                    'Limited Data Access (No PII)' => self::LIMITED_DATA_ACCESS,
                    'Download Disabled' => self::DOWNLOAD_DISABLED
                ],
                'multiple' => false,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ]
            ])
            ->add('ehr_modification_protocol', Type\ChoiceType::class, [
                'label' => 'EHR modification protocol',
                'required' => false,
                'choices' => [
                    'Yes'=> 1,
                    'No' => 0
                ],
                'multiple' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Site::class,
            'isDisabled' => false,
            'isProd' => false
        ]);
    }
}
