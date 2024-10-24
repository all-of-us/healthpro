<?php

namespace App\Form;

use App\Entity\Site;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class SiteType extends AbstractType
{
    public const FIXED_ANGLE = 'fixed_angle';
    public const SWINGING_BUCKET = 'swinging_bucket';
    public const FULL_DATA_ACCESS = 'full_data';
    public const LIMITED_DATA_ACCESS = 'limited_data';
    public const DOWNLOAD_DISABLED = 'disabled';
    public const DV_HYBRID = 'hybrid';

    public static $siteChoices = [
        'status' => [
            'Active' => 1,
            'Inactive' => 0
        ],
        'dv_module' => [
            'Default (Based on HOS selection)' => null,
            'DV Hybrid (Abbreviated PM Form + Kit)' => self::DV_HYBRID
        ],
        'centrifuge_type' => [
            'Fixed Angle' => self::FIXED_ANGLE,
            'Swinging Bucket' => self::SWINGING_BUCKET
        ],
        'ehr_modification_protocol' => [
            'No' => 0,
            'Yes' => 1
        ]
    ];


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
                'choices' => self::$siteChoices['status'],
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
            ]);

        if ($builder->getData() && $builder->getData()->getType() === 'DV') {
            $builder->add('dv_module', Type\ChoiceType::class, [
                'label' => 'DV Module Configuration',
                'required' => false,
                'choices' => self::$siteChoices['dv_module'],
                'multiple' => false
            ]);
        }

        $builder
            ->add('centrifuge_type', Type\ChoiceType::class, [
                'label' => 'Centrifuge type',
                'required' => false,
                'choices' => self::$siteChoices['centrifuge_type'],
                'multiple' => false,
                'placeholder' => '-- Select centrifuge type --'
            ])
            ->add('ehr_modification_protocol', Type\ChoiceType::class, [
                'label' => 'EHR modification protocol',
                'required' => true,
                'choices' => self::$siteChoices['ehr_modification_protocol'],
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
