<?php

namespace App\Form;

use App\Entity\Order;
use App\Entity\Site;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $disabled = $options['order']->isFormDisabled();
        switch ($options['step']) {
            case 'collected':
                $verb = 'collected';
                $noun = 'collection';
                break;
            case 'processed':
                $verb = 'processed';
                $noun = 'processing';
                break;
            case 'finalized':
                $verb = 'finalized';
                $noun = 'finalization';
                break;
            default:
                $verb = $options['step'];
                $adjective = $verb;
                $noun = "$adjective samples";
        }
        $tsLabel = ucfirst($verb) . ' time';
        $samplesLabel = "Which samples were successfully {$verb}?";
        $notesLabel = "Additional notes on {$noun}";
        if ($options['step'] == 'finalized') {
            $samplesLabel = "Which samples are being shipped to the All of Us℠ Biobank?";
        }
        if ($options['step'] == 'processed') {
            $tsLabel = 'Time of blood processing completion';
        }
        if ($options['step'] == 'processed') {
            $samples = array_intersect($options['order']->getCustomRequestedSamples(), Order::$samplesRequiringProcessing);
        } else {
            $samples = $options['order']->getCustomRequestedSamples();
        }
        if ($options['step'] == 'collected' && $options['order']->hasBloodSample($samples)) {
            $tsLabel = 'Blood Collection Time';
        }
        $enabledSamples = $options['order']->getEnabledSamples($options['step']);
        $constraintDateTime = new \DateTime('+5 minutes'); // add buffer for time skew
        if ($options['step'] != 'processed') {
            $constraints = [
                new Constraints\LessThanOrEqual([
                    'value' => $constraintDateTime,
                    'message' => 'Timestamp cannot be in the future'
                ])
            ];
            if ($options['step'] === 'finalized') {
                array_push(
                    $constraints,
                    new Constraints\GreaterThan([
                        'value' => $options['order']->getCollectedTs(),
                        'message' => 'Finalized Time is before Collection Time'
                    ])
                );
                $processedSamplesTs = json_decode($options['order']->getProcessedSamplesTs(), true);
                if (!empty($processedSamplesTs)) {
                    $processedTs = new \DateTime();
                    $processedTs->setTimestamp(max($processedSamplesTs));
                    $processedTs->setTimezone(new \DateTimeZone($options['timeZone']));
                    array_push(
                        $constraints,
                        new Constraints\GreaterThan([
                            'value' => $processedTs,
                            'message' => 'Finalized Time is before Processing Time'
                        ])
                    );
                }
            }
            $builder->add("{$options['step']}Ts", Type\DateTimeType::class, [
                'label' => $tsLabel,
                'widget' => 'single_text',
                'format' => 'M/d/yyyy h:mm a',
                'required' => false,
                'disabled' => $disabled,
                'view_timezone' => $options['timeZone'],
                'model_timezone' => 'UTC',
                'constraints' => $constraints
            ]);
        }
        if (!empty($samples)) {
            // Disable collected samples when mayo_id is set
            $samplesDisabled = $disabled;
            if ($options['step'] === 'collected' && $options['order']->getMayoId() && $options['order']->getStatus() !== $options['order']::ORDER_UNLOCK) {
                $samplesDisabled = true;
            }
            $builder->add("{$options['step']}Samples", Type\ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => $samplesLabel,
                'choices' => $samples,
                'required' => false,
                'disabled' => $samplesDisabled,
                'choice_attr' => function ($val) use ($enabledSamples, $options) {
                    $attr = [];
                    if ($options['step'] === 'finalized') {
                        $collectedSamples = json_decode($options['order']->getCollectedSamples(), true);
                        $processedSamples = json_decode($options['order']->getProcessedSamplesTs(), true);
                        if (in_array($val, $collectedSamples)) {
                            $attr = ['collected' => $options['order']->getCollectedTs()->setTimezone(new \DateTimeZone($options['timeZone']))->format('n/j/Y g:ia')];
                        }
                        if (!empty($processedSamples[$val])) {
                            $time = new \DateTime();
                            $time->setTimestamp($processedSamples[$val]);
                            $time->setTimezone(new \DateTimeZone($options['timeZone']));
                            $attr['processed'] = $time->format('n/j/Y g:ia');
                        }
                        if (in_array($val, Order::$samplesRequiringProcessing) && in_array($val, $collectedSamples)) {
                            $attr['required-processing'] = 'yes';
                        }
                    }
                    if ($options['step'] === 'processed' || $options['step'] === 'finalized') {
                        $warnings = $options['order']->getWarnings();
                        $errors = $options['order']->getErrors();
                        if (array_key_exists($val, Order::$sampleMessageLabels)) {
                            $type = Order::$sampleMessageLabels[$val];
                            if (!empty($errors[$type])) {
                                $attr['error'] = $errors[$type];
                            } elseif (!empty($warnings[$type])) {
                                $attr['warning'] = $warnings[$type];
                            }
                        }
                    }
                    if (in_array($val, $enabledSamples)) {
                        return $attr;
                    } else {
                        $attr['disabled'] = true;
                        $attr['class'] = 'sample-disabled';
                        return $attr;
                    }
                }
            ]);
        }
        if ($options['step'] == 'processed') {
            $builder->add('processedSamplesTs', Type\CollectionType::class, [
                'entry_type' => Type\DateTimeType::class,
                'label' => false,
                'disabled' => $disabled,
                'entry_options' => [
                    'widget' => 'single_text',
                    'format' => 'M/d/yyyy h:mm a',
                    'view_timezone' => $options['timeZone'],
                    'model_timezone' => 'UTC',
                    'label' => false,
                    'constraints' => [
                        new Constraints\LessThanOrEqual([
                            'value' => $constraintDateTime,
                            'message' => 'Timestamp cannot be in the future'
                        ])
                    ]
                ],
                'required' => false
            ]);
            // Display centrifuge type for kit orders only
            if ($options['order']->getType() === 'kit') {
                $sites = $options['em']->getRepository(Site::class)->findOneBy([
                    'deleted' => 0,
                    'googleGroup' => $options['siteId']
                ]);
                if (!empty($enabledSamples) && empty($sites->getCentrifugeType())) {
                    $builder->add('processedCentrifugeType', Type\ChoiceType::class, [
                        'label' => 'Centrifuge type',
                        'required' => true,
                        'disabled' => $disabled,
                        'choices' => [
                            '-- Select centrifuge type --' => null,
                            'Fixed Angle' => $options['order']::FIXED_ANGLE,
                            'Swinging Bucket' => $options['order']::SWINGING_BUCKET
                        ],
                        'multiple' => false,
                        'constraints' => new Constraints\NotBlank([
                            'message' => 'Please select centrifuge type'
                        ])
                    ]);
                }
            }
        }
        // Display fedex tracking for kit and diversion type orders
        if ($options['step'] === 'finalized' && ($options['order']->getType() === 'kit' || $options['order']->getType() === 'diversion')) {
            $builder->add('fedexTracking', Type\RepeatedType::class, [
                'type' => Type\TextType::class,
                'disabled' => $disabled,
                'invalid_message' => 'Tracking numbers must match.',
                'first_options' => [
                    'label' => 'FedEx or UPS tracking number (optional)'
                ],
                'second_options' => [
                    'label' => 'Verify tracking number',
                ],
                'required' => false,
                'error_mapping' => [
                    '.' => 'second' // target the second (repeated) field for non-matching error
                ],
                'constraints' => [
                    new Constraints\Type('string'),
                    new Constraints\Regex([
                        'pattern' => '/^\d{12,14}$|^[a-zA-Z0-9]{18}$/',
                        'message' => 'Tracking numbers must be a string of 12 to 14 digits for FedEX and 18 digits for UPS'
                    ])
                ]
            ]);
        }
        $builder->add("{$options['step']}Notes", Type\TextareaType::class, [
            'label' => $notesLabel,
            'disabled' => $disabled,
            'required' => false,
            'constraints' => new Constraints\Type('string')
        ]);
        $form = $builder->getForm();
        return $form;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'step' => null,
            'order' => null,
            'em' => null,
            'timeZone' => null,
            'siteId' => null
        ]);
    }
}
