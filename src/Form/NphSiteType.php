<?php

namespace App\Form;

use App\Entity\NphSite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Validation;

class NphSiteType extends AbstractType
{
    public const FIXED_ANGLE = 'fixed_angle';
    public const SWINGING_BUCKET = 'swinging_bucket';

    public static $siteChoices = [
        'status' => [
            'Active'=> 1,
            'Inactive' => 0
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
                ]
            ])
            ->add('status', Type\ChoiceType::class, [
                'label' => 'Status',
                'required' => true,
                'choices' => self::$siteChoices['status']
            ])
            ->add('google_group', Type\TextType::class, [
                'label' => 'Google Group',
                'required' => true,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ]
            ])
            ->add('organization_id', Type\TextType::class, [
                'label' => 'Organization',
                'required' => false,
                'constraints' => new Constraints\Type('string')
            ])
            ->add('awardee_id', Type\TextType::class, [
                'label' => 'Awardee',
                'required' => false,
                'constraints' => new Constraints\Type('string')
            ])
            ->add('mayolink_account', Type\TextType::class, [
                'label' => 'MayoLINK Account',
                'required' => false,
                'constraints' => new Constraints\Type('string')
            ])
            ->add('type', Type\TextType::class, [
                'label' => 'Type (e.g. HPO)',
                'required' => false,
                'constraints' => new Constraints\Type('string')
            ])
            ->add('email', Type\TextType::class, [
                'label' => 'Email address(es)',
                'required' => false,
                'constraints' => [
                    new Constraints\Type('string'),
                    new Constraints\Length(['max' => 512]),
                    new Constraints\Callback(function ($list, $context) {
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
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => NphSite::class
        ]);
    }
}
