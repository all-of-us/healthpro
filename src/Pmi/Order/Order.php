<?php
namespace Pmi\Order;

use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Validator\Constraints;
use Pmi\Audit\Log;
use Pmi\Order\Mayolink\MayolinkOrder;

class Order
{
    const FIXED_ANGLE = 'fixed_angle';
    const SWINGING_BUCKET = 'swinging_bucket';
    const ORDER_ACTIVE = 'active';
    const ORDER_CANCEL = 'cancel';
    const ORDER_RESTORE = 'restore';
    const ORDER_UNLOCK = 'unlock';
    const ORDER_EDIT = 'edit';
    const ORDER_REVERT = 'revert';

    protected $app;
    protected $order;
    protected $participant;

    public $samples;
    public $samplesInformation;
    public $salivaSamples;
    public $salivaSamplesInformation;
    public $salivaInstructions;
    public $version = 2;

    public static $samplesRequiringProcessing = ['1SST8', '1PST8', '1SS08', '1PS08', '1SAL', '1SAL2'];

    public static $samplesRequiringCentrifugeType = ['1SS08', '1PS08'];

    public static $identifierLabel = [
        'name' => 'name',
        'dob' => 'date of birth',
        'phone' => 'phone number',
        'address' => 'street address',
        'email' => 'email address'
    ];

    public static $centrifugeType = [
        'swinging_bucket' => 'Swinging Bucket',
        'fixed_angle' => 'Fixed Angle'
    ];

    public static $sst = ['1SST8', '1SS08'];

    public static $pst = ['1PST8', '1PS08'];

    public static $sampleMessageLabels = [
        '1SST8' => 'sst',
        '1SS08' => 'sst',
        '1PST8' => 'pst',
        '1PS08' => 'pst',
        '1SAL' => 'sal',
        '1SAL2' => 'sal'
    ];

    public static $nonBloodSamples = ['1UR10', '1UR90', '1SAL', '1SAL2'];

    public static $mapRdrSamples = [
        '1SST8' => [
            'code' => '1SS08',
            'centrifuge_type' => 'swinging_bucket'
        ],
        '2SST8' => [
            'code' => '1SS08',
            'centrifuge_type' => 'fixed_angle'
        ],
        '1PST8' => [
            'code' => '1PS08',
            'centrifuge_type' => 'swinging_bucket'
        ],
        '2PST8' => [
            'code' => '1PS08',
            'centrifuge_type' => 'fixed_angle'
        ]
    ];

    public static $cancelReasons = [
        'Order created in error' => 'ORDER_CANCEL_ERROR',
        'Order created for wrong participant' => 'ORDER_CANCEL_WRONG_PARTICIPANT',
        'Labeling error identified after finalization' => 'ORDER_CANCEL_LABEL_ERROR',
        'Other' => 'OTHER'
    ];

    public static $unlockReasons = [
        'Add/Remove collected or processed samples' => 'ORDER_AMEND_SAMPLES',
        'Change collection or processing timestamps' => 'ORDER_AMEND_TIMESTAMPS',
        'Change Tracking number' => 'ORDER_AMEND_TRACKING',
        'Other' => 'OTHER'
    ];

    public static $restoreReasons = [
        'Order cancelled for wrong participant' => 'ORDER_RESTORE_WRONG_PARTICIPANT',
        'Order can be amended instead of cancelled' => 'ORDER_RESTORE_AMEND',
        'Other' => 'OTHER'
    ];

    public function __construct($app = null)
    {
        if ($app) {
            $this->app = $app;
            if (!empty($app->getConfig('order_samples_version'))) {
                $this->version = $app->getConfig('order_samples_version');
            }
        }
        $this->loadSamplesSchema();
    }

    public function loadSamplesSchema()
    {
        $file = __DIR__ . "/versions/{$this->version}.json";
        if (!file_exists($file)) {
            throw new \Exception('Samples version file not found');
        }
        $schema = json_decode(file_get_contents($file), true);
        if (!is_array($schema) && !empty($schema)) {
            throw new \Exception('Invalid samples schema');
        }
        $this->samplesInformation = $schema['samplesInformation'];
        $samples = [];
        foreach ($this->samplesInformation as $sample => $info) {
            $label = "({$info['number']}) {$info['label']} [{$sample}]";
            $samples[$label] = $sample;
        }
        $this->samples = $samples;

        $this->salivaSamplesInformation = $schema['salivaSamplesInformation'];
        $salivaSamples = [];
        foreach ($this->salivaSamplesInformation as $salivaSample => $info) {
            $salivaSamples[$info['label']] = $salivaSample;
            $this->salivaSamplesInformation[$salivaSample]['sampleId'] = $salivaSample;
        }
        $this->salivaSamples = $salivaSamples;

        $this->salivaInstructions = $schema['salivaInstructions'];

        $this->setSampleIds();
    }

    public function setSampleIds()
    {
        foreach ($this->samplesInformation as $sample => $sampleInformation) {
            $sampleId = $sample;
            if (isset($sampleInformation['icodeSwingingBucket'])) {
                // For custom order creation (always display swinging bucket i-test codes)
                if (empty($this->order)) {
                    $sampleId = $sampleInformation['icodeSwingingBucket'];
                } elseif (!empty($this->order) && (empty($this->order['type']) || $this->order['type'] === 'diversion')) {
                    if ($this->order['processed_centrifuge_type'] === self::SWINGING_BUCKET) {
                        $sampleId = $sampleInformation['icodeSwingingBucket'];
                    } elseif ($this->order['processed_centrifuge_type'] === self::FIXED_ANGLE) {
                        $sampleId = $sampleInformation['icodeFixedAngle'];
                    }
                }
            }
            $this->samplesInformation[$sample]['sampleId'] = $sampleId;
        }
    }

    public function loadOrder($participantId, $orderId)
    {
        $participant = $this->app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            return;
        }
        $order = $this->getParticipantOrderWithHistory($orderId, $participantId);
        if (empty($order)) {
            return;
        }
        $this->order = $order[0];
        $this->order['expired'] = $this->isOrderExpired();
        $this->participant = $participant;
        $this->version = !empty($this->order['version']) ? $this->order['version'] : 1;
        $this->order['status'] = !empty($this->order['oh_type']) ? $this->order['oh_type'] : self::ORDER_ACTIVE;
        $this->order['reasonDisplayText'] = $this->getReasonDisplayText();
        $this->order['disabled'] = $this->isOrderDisabled();
        $this->order['formDisabled'] = $this->isOrderFormDisabled();
        $this->order['canCancel'] = $this->canCancel();
        $this->order['canRestore'] = $this->canRestore();
        $this->order['canUnlock'] = $this->canUnlock();
        $this->order['failedToReachRDR'] = $this->isOrderFailedToReachRdr();
        $this->loadSamplesSchema();
    }

    public function isValid()
    {
        return $this->order && $this->participant;
    }

    public function getParticipant()
    {
        return $this->participant;
    }

    public function setParticipant($participant)
    {
        $this->participant = $participant;
    }

    public function get($key)
    {
        if (isset($this->order[$key])) {
            return $this->order[$key];
        } else {
            return null;
        }
    }

    public function set($key, $value)
    {
        $this->order[$key] = $value;
    }

    public function toArray()
    {
        return $this->order;
    }

    public function setOrder($order)
    {
        $this->order = $order;
    }

    public function getCurrentStep()
    {
        $columns = [
            'printLabels' => 'printed',
            'collect' => 'collected',
            'process' => 'processed',
            'finalize' => 'finalized',
            'printRequisition' => 'finalized'
        ];
        if ($this->order['type'] === 'kit') {
            unset($columns['printLabels']);
            unset($columns['printRequisition']);
        }
        $step = 'finalize';
        foreach ($columns as $name => $column) {
            if (!$this->order["{$column}_ts"]) {
                $step = $name;
                break;
            }
        }
        // For canceled orders set print labels step to collect
        if ($this->isOrderCancelled() && $step === 'printLabels') {
            return 'collect';
        }
        return $step;
    }

    public function getAvailableSteps()
    {
        $columns = [
            'printLabels' => 'printed',
            'collect' => 'collected',
            'process' => 'processed',
            'finalize' => 'finalized',
            'printRequisition' => 'finalized'
        ];
        if ($this->order['type'] === 'kit') {
            unset($columns['printLabels']);
            unset($columns['printRequisition']);
        }
        $steps = [];
        foreach ($columns as $name => $column) {
            $steps[] = $name;
            if (!$this->order["{$column}_ts"]) {
                break;
            }
        }
        // For canceled orders include collect in available steps if not exists
        if ($this->isOrderCancelled() && !in_array('collect', $steps)) {
            $steps[] = 'collect';
        }
        return $steps;
    }

    public function getOrderUpdateFromForm($set, $form)
    {
        $updateArray = [];
        $formData = $form->getData();
        if ($formData["{$set}_notes"]) {
            $updateArray["{$set}_notes"] = $formData["{$set}_notes"];
        } else {
            $updateArray["{$set}_notes"] = null;
        }
        if ($set != 'processed') {
            if ($formData["{$set}_ts"]) {
                $updateArray["{$set}_ts"] = $formData["{$set}_ts"];
            } else {
                $updateArray["{$set}_ts"] = null;
            }
        }
        if ($form->has("{$set}_samples")) {
            $hasSampleArray = $formData["{$set}_samples"] && is_array($formData["{$set}_samples"]);
            $samples = [];
            if ($hasSampleArray) {
                $samples = array_values($formData["{$set}_samples"]);
            }
            $updateArray["{$set}_samples"] = json_encode($samples);
            if ($set === 'collected') {
                // Remove processed samples when not collected
                if (!empty($this->order['processed_samples_ts'])) {
                    $newProcessedSamples = $this->getNewProcessedSamples($samples);
                    $updateArray["processed_samples"] = $newProcessedSamples['samples'];
                    $updateArray["processed_samples_ts"] = $newProcessedSamples['timeStamps'];
                }
                // Remove finalized samples when not collected
                if (!empty($this->order['finalized_samples'])) {
                    $newFinalizedSamples = $this->getNewFinalizedSamples('collected', $samples);
                    $updateArray["finalized_samples"] = $newFinalizedSamples;
                }
            }
            if ($set === 'processed') {
                $hasSampleTimeArray = $formData['processed_samples_ts'] && is_array($formData['processed_samples_ts']);
                if ($hasSampleArray && $hasSampleTimeArray) {
                    $processedSampleTimes = [];
                    foreach ($formData['processed_samples_ts'] as $sample => $dateTime) {
                        if ($dateTime && in_array($sample, $formData["{$set}_samples"])) {
                            $processedSampleTimes[$sample] = $dateTime->getTimestamp();
                        }
                    }
                    $updateArray['processed_samples_ts'] = json_encode($processedSampleTimes);
                } else {
                    $updateArray['processed_samples_ts'] = json_encode([]);
                }
                if ($this->order['type'] !== 'saliva' && !empty($formData["processed_centrifuge_type"])) {
                    $updateArray["processed_centrifuge_type"] = $formData["processed_centrifuge_type"];
                }
                // Remove finalized samples when not processed
                if (!empty($this->order['finalized_samples'])) {
                    $newFinalizedSamples = $this->getNewFinalizedSamples('processed', $samples);
                    $updateArray["finalized_samples"] = $newFinalizedSamples;
                }
            }
        }
        if ($set === 'finalized' && $this->order['type'] === 'kit') {
            $updateArray['fedex_tracking'] = $formData['fedex_tracking'];
        }
        return $updateArray;
    }

    public function createOrderForm($set, $formFactory)
    {
        $disabled = $this->isOrderFormDisabled();

        switch ($set) {
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
                $verb = $set;
                $adjective = $verb;
                $noun = "$adjective samples";
        }
        $tsLabel = ucfirst($verb) . ' time';
        $samplesLabel = "Which samples were successfully {$verb}?";
        $notesLabel = "Additional notes on {$noun}";
        if ($set == 'finalized') {
            $samplesLabel = "Which samples are being shipped to the All of Us℠ Biobank?";
        }
        if ($set == 'processed') {
            $tsLabel = 'Time of blood processing completion';
        }

        $formData = $this->getOrderFormData($set);
        if ($set == 'processed') {
            $samples = array_intersect($this->getRequestedSamples(), self::$samplesRequiringProcessing);
        } else {
            $samples = $this->getRequestedSamples();
        }
        if ($set == 'collected' && $this->hasBloodSample($samples)) {
            $tsLabel = 'Blood Collection Time';
        }
        $enabledSamples = $this->getEnabledSamples($set);
        $formBuilder = $formFactory->createBuilder(FormType::class, $formData);
        $constraintDateTime = new \DateTime('+5 minutes'); // add buffer for time skew
        if ($set != 'processed') {
            $constraints = [
                new Constraints\LessThanOrEqual([
                    'value' => $constraintDateTime,
                    'message' => 'Timestamp cannot be in the future'
                ])
            ];
            if ($set === 'finalized') {
                array_push($constraints,
                    new Constraints\GreaterThan([
                        'value' => $this->order['collected_ts'],
                        'message' => 'Finalized Time is before Collection Time'
                    ])
                );
                $processedSamplesTs = json_decode($this->order['processed_samples_ts'], true);
                if (!empty($processedSamplesTs)) {
                    $processedTs = new \DateTime();
                    $processedTs->setTimestamp(max($processedSamplesTs));
                    $processedTs->setTimezone(new \DateTimeZone($this->app->getUserTimezone()));
                    array_push($constraints,
                        new Constraints\GreaterThan([
                            'value' => $processedTs,
                            'message' => 'Finalized Time is before Processing Time'
                        ])
                    );
                }
            }
            $formBuilder->add("{$set}_ts", Type\DateTimeType::class, [
                'label' => $tsLabel,
                'widget' => 'single_text',
                'format' => 'M/d/yyyy h:mm a',
                'required' => false,
                'disabled' => $disabled,
                'view_timezone' => $this->app->getUserTimezone(),
                'model_timezone' => 'UTC',
                'constraints' => $constraints
            ]);
        }
        if (!empty($samples)) {
            // Disable collected samples when mayo_id is set
            $samplesDisabled = $disabled;
            if ($set === 'collected' && $this->order['mayo_id'] && $this->order['status'] !== self::ORDER_UNLOCK) {
                $samplesDisabled = true;
            }
            $formBuilder->add("{$set}_samples", Type\ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => $samplesLabel,
                'choices' => $samples,
                'required' => false,
                'disabled' => $samplesDisabled,
                'choice_attr' => function ($val) use ($enabledSamples, $set) {
                    $attr = [];
                    if ($set === 'finalized') {
                        $collectedSamples = json_decode($this->order['collected_samples'], true);
                        $processedSamples = json_decode($this->order['processed_samples_ts'], true);
                        if (in_array($val, $collectedSamples)) {
                            $attr = ['collected' => $this->order['collected_ts']->format('n/j/Y g:ia')];
                        }
                        if (!empty($processedSamples[$val])) {
                            $time = new \DateTime();
                            $time->setTimestamp($processedSamples[$val]);
                            $time->setTimezone(new \DateTimeZone($this->app->getUserTimezone()));
                            $attr['processed'] = $time->format('n/j/Y g:ia');
                        }
                        if (in_array($val, self::$samplesRequiringProcessing) && in_array($val, $collectedSamples)) {
                            $attr['required-processing'] = 'yes';
                        }
                    }
                    if ($set === 'processed' || $set === 'finalized') {
                        $warnings = $this->getWarnings();
                        $errors = $this->getErrors();
                        if (array_key_exists($val, self::$sampleMessageLabels)) {
                            $type = self::$sampleMessageLabels[$val];
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
        if ($set == 'processed') {
            $formBuilder->add('processed_samples_ts', Type\CollectionType::class, [
                'entry_type' => Type\DateTimeType::class,
                'label' => false,
                'disabled' => $disabled,
                'entry_options' => [
                    'date_widget' => 'single_text',
                    'time_widget' => 'single_text',
                    'widget' => 'single_text',
                    'format' => 'M/d/yyyy h:mm a',
                    'view_timezone' => $this->app->getUserTimezone(),
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
            if ($this->order['type'] === 'kit') {
                $sites = $this->app['em']->getRepository('sites')->fetchOneBy([
                    'deleted' => 0,
                    'google_group' => $this->app->getSiteId()
                ]);
                if (!empty($enabledSamples) && empty($sites['centrifuge_type'])) {
                    $formBuilder->add('processed_centrifuge_type', Type\ChoiceType::class, [
                        'label' => 'Centrifuge type',
                        'required' => true,
                        'disabled' => $disabled,
                        'choices' => [
                            '-- Select centrifuge type --' => null,
                            'Fixed Angle' => self::FIXED_ANGLE,
                            'Swinging Bucket' => self::SWINGING_BUCKET
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
        if ($set === 'finalized' && ($this->order['type'] === 'kit' || $this->order['type'] === 'diversion')) {
            $formBuilder->add('fedex_tracking', Type\RepeatedType::class, [
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
        $formBuilder->add("{$set}_notes", Type\TextareaType::class, [
            'label' => $notesLabel,
            'disabled' => $disabled,
            'required' => false,
            'constraints' => new Constraints\Type('string')
        ]);
        $form = $formBuilder->getForm();
        return $form;
    }

    public function getRdrObject($order = null)
    {
        if ($order) {
            $this->order = $order;
        }
        $obj = new \StdClass();
        $obj->subject = 'Patient/' . $this->order['participant_id'];
        $identifiers = [];
        $identifiers[] = [
            'system' => 'https://www.pmi-ops.org',
            'value' => $this->order['order_id']
        ];
        if ($this->order['type'] === 'kit') {
            $identifiers[] = [
                'system' => 'https://orders.mayomedicallaboratories.com/kit-id',
                'value' => $this->order['order_id']
            ];
            if (!empty($this->order['fedex_tracking'])) {
                $identifiers[] = [
                    'system' => 'https://orders.mayomedicallaboratories.com/tracking-number',
                    'value' => $this->order['fedex_tracking']
                ];
            }
        }
        if ($this->app) {
            if (!$this->app->getConfig('ml_mock_order') && $this->order['mayo_id'] != 'pmitest') {
                $identifiers[] = [
                    'system' => 'https://orders.mayomedicallaboratories.com',
                    'value' => $this->order['mayo_id']
                ];
            } else {
                $identifiers[] = [
                    'system' => 'https://orders.mayomedicallaboratories.com',
                    'value' => 'PMITEST-' . $this->order['order_id']
                ];
            }
            $createdUser = $this->getOrderUser($this->order['user_id']);
            $createdSite = $this->getOrderSite($this->order['site']);
            $collectedUser = $this->getOrderUser($this->order['collected_user_id']);
            $collectedSite = $this->getOrderSite($this->order['collected_site']);
            $processedUser = $this->getOrderUser($this->order['processed_user_id']);
            $processedSite = $this->getOrderSite($this->order['processed_site']);
            $finalizedUser = $this->getOrderUser($this->order['finalized_user_id']);
            $finalizedSite = $this->getOrderSite($this->order['finalized_site']);
            $obj->createdInfo = $this->getOrderUserSiteData($createdUser, $createdSite);
            $obj->collectedInfo = $this->getOrderUserSiteData($collectedUser, $collectedSite);
            $obj->processedInfo = $this->getOrderUserSiteData($processedUser, $processedSite);
            $obj->finalizedInfo = $this->getOrderUserSiteData($finalizedUser, $finalizedSite);
        }
        $obj->identifier = $identifiers;

        $created = clone $this->order['created_ts'];
        $created->setTimezone(new \DateTimeZone('UTC'));
        $obj->created = $created->format('Y-m-d\TH:i:s\Z');

        $obj->samples = $this->getRdrSamples();

        $notes = [];
        foreach (['collected', 'processed', 'finalized'] as $step) {
            if ($this->order[$step . '_notes']) {
                $notes[$step] = $this->order[$step . '_notes'];
            }
        }
        if (!empty($notes)) {
            $obj->notes = $notes;
        }
        return $obj;
    }

    public function getCancelRestoreRdrObject($type, $reason)
    {
        $obj = new \StdClass();
        $statusType = $type === self::ORDER_CANCEL ? 'cancelled' : 'restored';
        $obj->status = $statusType;
        $obj->amendedReason = $reason;
        $user = $this->getOrderUser($this->app->getUser()->getId());
        $site = $this->getOrderSite($this->app->getSiteId());
        $obj->{$statusType . 'Info'} = $this->getOrderUserSiteData($user, $site);
        return $obj;
    }

    public function getEditRdrObject()
    {
        $obj = $this->getRdrObject();
        $obj->amendedReason = $this->order['oh_reason'];
        $user = $this->getOrderUser($this->order['oh_user_id']);
        $site = $this->getOrderSite($this->order['oh_site']);
        $obj->amendedInfo = $this->getOrderUserSiteData($user, $site);
        return $obj;
    }

    public function sendToRdr()
    {
        if ($this->order['status'] === self::ORDER_UNLOCK) {
            return $this->editRdrOrder();
        } else {
            return $this->createRdrOrder();
        }
    }

    public function createRdrOrder()
    {
        $order = $this->getRdrObject();
        $rdrId = $this->app['pmi.drc.participants']->createOrder($this->participant->id, $order);
        if (!$rdrId) {
            // Check for rdr id conflict error code
            if ($this->app['pmi.drc.participants']->getLastErrorCode() === 409) {
                $rdrOrder = $this->app['pmi.drc.participants']->getOrder($this->order['participant_id'], $this->order['mayo_id']);
                // Check if order exists in RDR
                if (!empty($rdrOrder) && $rdrOrder->id === $this->order['mayo_id']) {
                    $rdrId = $this->order['mayo_id'];
                }
            }
        }
        if (!empty($rdrId)) {
            $this->app['em']->getRepository('orders')->update(
                $this->order['id'],
                ['rdr_id' => $rdrId]
            );
            return true;
        }
        return false;
    }

    public function cancelRestoreRdrOrder($type, $reason)
    {
        $order = $this->getCancelRestoreRdrObject($type, $reason);
        return $this->app['pmi.drc.participants']->cancelRestoreOrder($type, $this->participant->id, $this->order['rdr_id'], $order);
    }

    public function editRdrOrder()
    {
        $order = $this->getEditRdrObject();
        $status = $this->app['pmi.drc.participants']->editOrder($this->participant->id, $this->order['rdr_id'], $order);
        if ($status) {
            return $this->createOrderHistory(self::ORDER_EDIT);
        }
        return false;
    }

    protected function getSampleTime($set, $sample)
    {
        $samples = json_decode($this->order["{$set}_samples"]);
        if (!is_array($samples) || !in_array($sample, $samples)) {
            return false;
        }
        if ($set == 'processed') {
            $processedSampleTimes = json_decode($this->order['processed_samples_ts'], true);
            if (!empty($processedSampleTimes[$sample])) {
                try {
                    $time = new \DateTime();
                    $time->setTimestamp($processedSampleTimes[$sample]);
                    return $time->format('Y-m-d\TH:i:s\Z');
                } catch (\Exception $e) {
                }
            }
        } else {
            if ($this->order["{$set}_ts"]) {
                $time = clone $this->order["{$set}_ts"];
                $time->setTimezone(new \DateTimeZone('UTC'));
                return $time->format('Y-m-d\TH:i:s\Z');
            }
        }
    }

    protected function getRdrSamples()
    {
        $samples = [];
        foreach ($this->getRequestedSamples() as $description => $test) {
            // Convert new samples
            $rdrTest = $test;
            if ($test == '1SS08') {
                $rdrTest = ($this->order['processed_centrifuge_type'] == self::FIXED_ANGLE) ? '2SST8' : '1SST8';
            }
            if ($test == '1PS08') {
                $rdrTest = ($this->order['processed_centrifuge_type'] == self::FIXED_ANGLE) ? '2PST8' : '1PST8';
            }
            $sample = [
                'test' => $rdrTest,
                'description' => $description,
                'processingRequired' => in_array($test, self::$samplesRequiringProcessing)
            ];
            if ($collected = $this->getSampleTime('collected', $test)) {
                $sample['collected'] = $collected;
            }
            if ($sample['processingRequired']) {
                $processed = $this->getSampleTime('processed', $test);
                if ($processed) {
                    $sample['processed'] = $processed;
                }
            }
            if ($finalized = $this->getSampleTime('finalized', $test)) {
                $sample['finalized'] = $finalized;
            }
            $samples[] = $sample;
        }
        return $samples;
    }

    protected function getOrderFormData($set)
    {
        $formData = [];
        if ($this->order["{$set}_notes"]) {
            $formData["{$set}_notes"] = $this->order["{$set}_notes"];
        };
        if ($set != 'processed') {
            if ($this->order["{$set}_ts"]) {
                $formData["{$set}_ts"] = $this->order["{$set}_ts"];
            }
        }
        if ($this->order["{$set}_samples"]) {
            $samples = json_decode($this->order["{$set}_samples"]);
            if (is_array($samples) && count($samples) > 0) {
                $formData["{$set}_samples"] = $samples;
            }
        }
        if ($set == 'processed') {
            $processedSampleTimes = [];
            if (isset($this->order['processed_samples_ts'])) {
                $processedSampleTimes = json_decode($this->order['processed_samples_ts'], true);
            }
            foreach (self::$samplesRequiringProcessing as $sample) {
                if (!empty($processedSampleTimes[$sample])) {
                    try {
                        $sampleTs = new \DateTime();
                        $sampleTs->setTimestamp($processedSampleTimes[$sample]);
                        $sampleTs->setTimezone(new \DateTimeZone($this->app->getUserTimezone()));
                        $formData['processed_samples_ts'][$sample] = $sampleTs;
                    } catch (\Exception $e) {
                        $formData['processed_samples_ts'][$sample] = null;
                    }
                } else {
                    $formData['processed_samples_ts'][$sample] = null;
                }
            }
            if ($this->order["processed_centrifuge_type"]) {
                $formData["processed_centrifuge_type"] = $this->order["processed_centrifuge_type"];
            }
        }
        if ($set === 'finalized' && $this->order['type'] === 'kit') {
            $formData['fedex_tracking'] = $this->order['fedex_tracking'];
        }
        return $formData;
    }

    protected function getRequestedSamples()
    {
        if ($this->order['type'] == 'saliva') {
            return $this->salivaSamples;
        }
        if ($this->order['requested_samples'] &&
            ($requestedArray = json_decode($this->order['requested_samples'])) &&
            is_array($requestedArray) &&
            count($requestedArray) > 0
        ) {
            return array_intersect($this->samples, $requestedArray);
        } else {
            return $this->samples;
        }
    }

    protected function getEnabledSamples($set)
    {
        if ($this->order['collected_samples'] &&
            ($collectedArray = json_decode($this->order['collected_samples'])) &&
            is_array($collectedArray)
        ) {
            $collected = $collectedArray;
        } else {
            $collected = [];
        }

        if ($this->order['processed_samples'] &&
            ($processedArray = json_decode($this->order['processed_samples'])) &&
            is_array($processedArray)
        ) {
            $processed = $processedArray;
        } else {
            $processed = [];
        }

        switch ($set) {
            case 'processed':
                return array_intersect($collected, self::$samplesRequiringProcessing, $this->getRequestedSamples());
            case 'finalized':
                $enabled = array_intersect($collected, $this->getRequestedSamples());
                foreach ($enabled as $key => $sample) {
                    if (in_array($sample, self::$samplesRequiringProcessing) &&
                        !in_array($sample, $processed)
                    ) {
                        unset($enabled[$key]);
                    }
                }
                return array_values($enabled);
            default:
                return array_values($this->getRequestedSamples());
        }
    }

    protected function getOrderUser($userId)
    {
        if ($this->order['biobank_finalized'] && empty($userId)) {
            return 'BiobankUser';
        }
        $userId = $userId ?: $this->order['user_id'];
        $user = $this->app['em']->getRepository('users')->fetchOneBy([
            'id' => $userId
        ]);
        return $user['email'] ?? '';
    }

    protected function getOrderSite($site)
    {
        $site = $site ?: $this->order['site'];
        return \Pmi\Security\User::SITE_PREFIX . $site;
    }

    protected function getOrderUserSiteData($user, $site)
    {
        return [
            'author' => [
                'system' => 'https://www.pmi-ops.org/healthpro-username',
                'value' => $user
            ],
            'site' => [
                'system' => 'https://www.pmi-ops.org/site-id',
                'value' => $site
            ]
        ];
    }

    public function checkIdentifiers($notes)
    {
        return $this->getParticipant()->checkIdentifiers($notes);
    }

    public function getWarnings()
    {
        $warnings = [];
        if ($this->order['type'] !== 'saliva' && !empty($this->order['collected_ts']) && !empty($this->order['processed_samples_ts'])) {
            $collectedTs = clone $this->order['collected_ts'];
            $processedSamples = json_decode($this->order['processed_samples'], true);
            $processedSamplesTs = json_decode($this->order['processed_samples_ts'], true);
            $sst = array_values(array_intersect($processedSamples, self::$sst));
            $pst = array_values(array_intersect($processedSamples, self::$pst));
            //Check if SST processing time is less than 30 mins after collection time
            $collectedTs->modify('+30 minutes');
            if (!empty($sst) && !empty($processedSamplesTs[$sst[0]]) && $processedSamplesTs[$sst[0]] < $collectedTs->getTimestamp()) {
                $warnings['sst'] = 'SST Specimen Processed Less than 30 minutes after Collection';
            }
            //Check if SST processing time is greater than 4 hrs after collection time
            $collectedTs->modify('+210 minutes');
            if (!empty($sst) && !empty($processedSamplesTs[$sst[0]]) && $processedSamplesTs[$sst[0]] > $collectedTs->getTimestamp()) {
                $warnings['sst'] = 'Processing Time is Greater than 4 hours after Collection';
            }
            //Check if PST processing time is greater than 4 hrs after collection time
            if (!empty($pst) && !empty($processedSamplesTs[$pst[0]]) && $processedSamplesTs[$pst[0]] > $collectedTs->getTimestamp()) {
                $warnings['pst'] = 'Processing Time is Greater than 4 hours after Collection';
            }
        }
        return $warnings;
    }

    public function getErrors()
    {
        $errors = [];
        if (!empty($this->order['collected_ts']) && !empty($this->order['processed_samples_ts'])) {
            $collectedTs = clone $this->order['collected_ts'];
            $processedSamples = json_decode($this->order['processed_samples'], true);
            $processedSamplesTs = json_decode($this->order['processed_samples_ts'], true);
            $sst = array_values(array_intersect($processedSamples, self::$sst));
            $pst = array_values(array_intersect($processedSamples, self::$pst));
            $sal = array_values(array_intersect($processedSamples, $this->salivaSamples));
            //Check if SST processing time is less than collection time
            if (!empty($sst) && !empty($processedSamplesTs[$sst[0]]) && $processedSamplesTs[$sst[0]] <= $collectedTs->getTimestamp()) {
                $errors['sst'] = 'SST Processing Time is before Collection Time';
            }
            //Check if PST processing time is less than collection time
            if (!empty($pst) && !empty($processedSamplesTs[$pst[0]]) && $processedSamplesTs[$pst[0]] <= $collectedTs->getTimestamp()) {
                $errors['pst'] = 'PST Processing Time is before Collection Time';
            }
            //Check if SAL processing time is less than collection time
            if (!empty($sal) && !empty($processedSamplesTs[$sal[0]]) && $processedSamplesTs[$sal[0]] <= $collectedTs->getTimestamp()) {
                $errors['sal'] = 'SAL Processing Time is before Collection Time';
            }
        }
        return $errors;
    }

    public function getProcessTabClass()
    {
        $class = 'fa fa-check-circle text-success';
        if (!empty($this->getErrors())) {
            $class = 'fa fa-exclamation-circle text-danger';
        } elseif (!empty($this->getWarnings())) {
            $class = 'fa fa-exclamation-triangle text-warning';
        }
        return $class;
    }

    public function isOrderExpired()
    {
        return empty($this->order['finalized_ts']) && empty($this->order['version']);
    }

    // Finalized form is only disabled when rdr_id is set
    public function isOrderDisabled()
    {
        return ($this->order['rdr_id'] || $this->order['expired'] || $this->isOrderCancelled()) && $this->order['status'] !== 'unlock';
    }

    // Except finalize form all forms are disabled when finalized_ts is set
    public function isOrderFormDisabled()
    {
        return ($this->order['finalized_ts'] || $this->order['expired'] || $this->isOrderCancelled()) && $this->order['status'] !== 'unlock';
    }

    public function isOrderCancelled()
    {
        return $this->order['status'] === self::ORDER_CANCEL;
    }

    public function isOrderUnlocked()
    {
        return $this->order['status'] === self::ORDER_UNLOCK;
    }

    public function isOrderFailedToReachRdr()
    {
        return !empty($this->order['finalized_ts']) && !empty($this->order['mayo_id']) && empty($this->order['rdr_id']);
    }

    public function canCancel()
    {
        return !$this->isOrderCancelled() && !$this->isOrderUnlocked() && !$this->isOrderFailedToReachRdr();
    }

    public function canRestore()
    {
        return !$this->isOrderExpired() && $this->isOrderCancelled() && !$this->isOrderUnlocked() && !$this->isOrderFailedToReachRdr();
    }

    public function canUnlock()
    {
        return !$this->isOrderExpired() && !empty($this->order['rdr_id']) && !$this->isOrderUnlocked() && !$this->isOrderCancelled();
    }

    public function hasBloodSample($samples)
    {
        foreach ($samples as $sampleCode) {
            if (!in_array($sampleCode, self::$nonBloodSamples)) {
                return true;
            }
        }
        return false;
    }

    public function getUrineSample()
    {
        foreach ($this->samples as $sample) {
            if (in_array($sample, Order::$nonBloodSamples)) {
                return $sample;
            }
        }
        return null;
    }

    private function getNumericId()
    {
        $length = 10;
        // Avoid leading 0s
        $id = (string)rand(1, 9);
        for ($i = 0; $i < $length - 1; $i++) {
            $id .= (string)rand(0, 9);
        }
        return $id;
    }

    public function generateId()
    {
        $attempts = 0;
        $ordersRepository = $this->app['em']->getRepository('orders');
        while (++$attempts <= 20) {
            $id = $this->getNumericId();
            if ($ordersRepository->fetchOneBy(['order_id' => $id])) {
                $id = null;
            } else {
                break;
            }
        }
        if (is_null($id)) {
            throw new \Exception('Failed to generate unique order id');
        }
        return $id;
    }

    public function getSamplesInfo()
    {
        $samples = [];
        $samplesInfo = $this->order['type'] === 'saliva' ? $this->salivaSamplesInformation : $this->samplesInformation;
        foreach ($this->getRequestedSamples() as $key => $value) {
            $sample = [
                'code' => $key,
                'color' => isset($samplesInfo[$value]['color']) ? $samplesInfo[$value]['color'] : ''
            ];
            if (!empty($this->order['collected_ts'])) {
                $sample['collected_ts'] = $this->order['collected_ts'];
            }
            if (!empty($this->order['collected_samples']) && in_array($value, json_decode($this->order['collected_samples']))) {
                $sample['collected_checked'] = true;
            }
            if (!empty($this->order['finalized_ts'])) {
                $sample['finalized_ts'] = $this->order['finalized_ts'];
            }
            if (!empty($this->order['finalized_samples']) && in_array($value, json_decode($this->order['finalized_samples']))) {
                $sample['finalized_checked'] = true;
            }
            if (!empty($this->order['processed_samples_ts'])) {
                $processedSamplesTs = json_decode($this->order['processed_samples_ts'], true);
                if (!empty($processedSamplesTs[$value])) {
                    $processedTs = new \DateTime();
                    $processedTs->setTimestamp($processedSamplesTs[$value]);
                    $processedTs->setTimezone(new \DateTimeZone($this->app->getUserTimezone()));
                    $sample['processed_ts'] = $processedTs;
                }
            }
            if (!empty($this->order['processed_samples']) && in_array($value, json_decode($this->order['processed_samples']))) {
                $sample['processed_checked'] = true;
            }
            if (in_array($value, self::$samplesRequiringProcessing)) {
                $sample['process'] = true;
            }
            $samples[] = $sample;
        }
        return $samples;
    }

    public function getNewProcessedSamples($samples)
    {
        $processedSamplesTs = json_decode($this->order['processed_samples_ts'], true);
        $newProcessedSamples = [];
        $newProcessedSamplesTs = [];
        foreach ($processedSamplesTs as $sample => $timestamp) {
            // Check if each processed sample exists in collected samples list
            if (in_array($sample, $samples)) {
                $newProcessedSamples[] = $sample;
                $newProcessedSamplesTs[$sample] = $timestamp;
            }
        }
        return [
            'samples' => json_encode($newProcessedSamples),
            'timeStamps' => json_encode($newProcessedSamplesTs)
        ];
    }

    public function getNewFinalizedSamples($type, $samples)
    {
        $finalizedSamples = json_decode($this->order['finalized_samples'], true);
        $newFinalizedSamples = [];
        if ($type === 'collected') {
            foreach ($finalizedSamples as $sample) {
                // Check if each finalized sample exists in collected samples list
                if (in_array($sample, $samples)) {
                    $newFinalizedSamples[] = $sample;
                }
            }
        } elseif ($type === 'processed') {
            // Determine processing samples which needs to be removed
            $processedSamples = array_intersect($finalizedSamples, self::$samplesRequiringProcessing);
            $removeProcessedSamples = [];
            foreach ($processedSamples as $processedSample) {
                if (!in_array($processedSample, $samples)) {
                    $removeProcessedSamples[] = $processedSample;
                }
            }
            // Remove processing samples which are not processed
            if (!empty($removeProcessedSamples)) {
                foreach ($finalizedSamples as $key => $sample) {
                    if (in_array($sample, $removeProcessedSamples)) {
                        unset($finalizedSamples[$key]);
                    }
                }
            }
            $newFinalizedSamples = array_values($finalizedSamples);
        }
        return json_encode($newFinalizedSamples);
    }

    public function getOrderModifyForm($type)
    {
        $orderModifyForm = $this->app['form.factory']->createBuilder(Type\FormType::class, null);
        $reasonType = $type . 'Reasons';
        $reasons = self::$$reasonType;
        // Remove change tracking number option for non-kit orders
        if ($type === self::ORDER_UNLOCK && $this->order['type'] !== 'kit') {
            if (($key = array_search('ORDER_AMEND_TRACKING', $reasons)) !== false) {
                unset($reasons[$key]);
            }
        }
        // Remove label error option for kit orders
        if ($type === self::ORDER_CANCEL && $this->order['type'] === 'kit') {
            if (($key = array_search('ORDER_CANCEL_LABEL_ERROR', $reasons)) !== false) {
                unset($reasons[$key]);
            }
        }
        $orderModifyForm->add('reason', Type\ChoiceType::class, [
            'label' => 'Reason',
            'required' => true,
            'choices' => $reasons,
            'placeholder' => '-- Select ' . ucfirst($type) . ' Reason --',
            'multiple' => false,
            'constraints' => new Constraints\NotBlank([
                'message' => "Please select {$type} reason"
            ])
        ]);
        $orderModifyForm->add('other_text', Type\TextareaType::class, [
            'label' => false,
            'required' => false,
            'constraints' => [
                new Constraints\Type('string')
            ]
        ]);
        if ($type == self::ORDER_CANCEL) {
            $orderModifyForm->add('confirm', Type\TextType::class, [
                'label' => 'Confirm',
                'required' => true,
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => 'Type the word "CANCEL" to confirm',
                    'autocomplete' => 'off'
                ]
            ]);
        }
        return $orderModifyForm->getForm();
    }

    public function createOrderHistory($type, $reason = '')
    {
        $orderHistoryData = [
            'reason' => $reason,
            'order_id' => $this->order['id'],
            'user_id' => $this->app->getUser()->getId(),
            'site' => $this->app->getSiteId(),
            'type' => $type === self::ORDER_REVERT ? self::ORDER_ACTIVE : $type,
            'created_ts' => new \DateTime()
        ];
        $ordersHistoryRepository = $this->app['em']->getRepository('orders_history');
        $status = false;
        $ordersHistoryRepository->wrapInTransaction(function () use ($ordersHistoryRepository, $orderHistoryData, &$status, $type) {
            $id = $ordersHistoryRepository->insert($orderHistoryData);
            $this->app->log(Log::ORDER_HISTORY_CREATE, [
                'id' => $id,
                'type' => $orderHistoryData['type']
            ]);

            //Get most recent order edit history id if exists while restoring or reverting an order
            if ($type === self::ORDER_REVERT || $type === self::ORDER_RESTORE) {
                $orderHistory = $this->app['em']
                    ->getRepository('orders_history')
                    ->fetchBy(['order_id' => $this->order['id'], 'type' => self::ORDER_EDIT], ['id' => 'DESC'], 1);

                //Set edit history id if exists
                if (!empty($orderHistory)) {
                    $id = $orderHistory[0]['id'];
                }
            }

            //Update history id in orders table
            $this->app['em']->getRepository('orders')->update(
                $this->order['id'],
                ['history_id' => $id]
            );
            $status = true;
        });
        return $status;
    }

    public function getParticipantOrderWithHistory($orderId, $participantId)
    {
        $ordersQuery = "
            SELECT o.*,
                   oh.order_id AS oh_order_id,
                   oh.user_id AS oh_user_id,
                   oh.site AS oh_site,
                   oh.type AS oh_type,
                   oh.reason AS oh_reason,
                   oh.created_ts AS oh_created_ts
            FROM orders o
            LEFT JOIN orders_history oh ON o.history_id = oh.id
            WHERE o.id = :orderId
              AND o.participant_id = :participantId
            ORDER BY o.id DESC
        ";
        return $this->app['em']->fetchAll($ordersQuery, [
            'orderId' => $orderId,
            'participantId' => $participantId
        ]);
    }

    public function getOrderRevertForm()
    {
        $orderRevertForm = $this->app['form.factory']->createBuilder(Type\FormType::class, null);
        $orderRevertForm->add('revert', Type\SubmitType::class, [
            'label' => 'Revert',
            'attr' => [
                'class' => 'btn-warning'
            ]
        ]);
        return $orderRevertForm->getForm();
    }

    /**
     * Revert collected, processed, finalized samples and timestamps
     */
    public function revertOrder($participantId)
    {
        // Get order object from RDR
        $object = $this->app['pmi.drc.participants']->getOrder($participantId, $this->order['rdr_id']);

        //Update samples
        if (!empty($object->samples)) {
            foreach ($object->samples as $sample) {
                $sampleCode = $sample->test;
                if (!array_key_exists($sample->test, $this->samplesInformation) && array_key_exists($sample->test, self::$mapRdrSamples)) {
                    $sampleCode = self::$mapRdrSamples[$sample->test]['code'];
                    $centrifugeType = self::$mapRdrSamples[$sample->test]['centrifuge_type'];
                }
                if (!empty($sample->collected)) {
                    $collectedSamples[] = $sampleCode;
                    $collectedTs = $sample->collected;
                }
                if (!empty($sample->processed)) {
                    $processedSamples[] = $sampleCode;
                    $processedTs = new \DateTime($sample->processed);
                    $processedSamplesTs[$sampleCode] = $processedTs->getTimestamp();
                }
                if (!empty($sample->finalized)) {
                    $finalizedSamples[] = $sampleCode;
                    $finalizedTs = $sample->finalized;
                }
            }
        }

        // Update notes field
        $collectedNotes = !empty($object->notes->collected) ? $object->notes->collected : null;
        $processedNotes = !empty($object->notes->processed) ? $object->notes->processed : null;
        $finalizedNotes = !empty($object->notes->finalized) ? $object->notes->finalized : null;

        // Update tracking number
        if (!empty($object->identifier)) {
            foreach ($object->identifier as $identifier) {
                if (preg_match("/tracking-number/i", $identifier->system)) {
                    $trackingNumber = $identifier->value;
                    break;
                }
            }
        }
        $updateArray = [
            'collected_samples' => json_encode(!empty($collectedSamples) ? $collectedSamples : []),
            'collected_ts' => !empty($collectedTs) ? $collectedTs : null,
            'processed_samples' => json_encode(!empty($processedSamples) ? $processedSamples : []),
            'processed_samples_ts' => json_encode(!empty($processedSamplesTs) ? $processedSamplesTs : []),
            'finalized_samples' => json_encode(!empty($finalizedSamples) ? $finalizedSamples : []),
            'finalized_ts' => !empty($finalizedTs) ? $finalizedTs : null,
            'collected_notes' => $collectedNotes,
            'processed_notes' => $processedNotes,
            'finalized_notes' => $finalizedNotes,
            'fedex_tracking' => !empty($trackingNumber) ? $trackingNumber : null
        ];

        //Update centrifuge type
        if (!empty($centrifugeType)) {
            $updateArray['processed_centrifuge_type'] = $centrifugeType;
        }

        // Update order
        $ordersRepository = $this->app['em']->getRepository('orders');
        $status = false;
        $ordersRepository->wrapInTransaction(function () use ($ordersRepository, $updateArray, &$status) {
            $ordersRepository->update($this->order['id'], $updateArray);
            $this->createOrderHistory(self::ORDER_REVERT);
            $status = true;
        });
        return $status;
    }

    public function getReasonDisplayText()
    {
        if (empty($this->order['oh_reason'])) {
            return null;
        }
        // Check only cancel reasons
        $reasonDisplayText = array_search($this->order['oh_reason'], self::$cancelReasons);
        return !empty($reasonDisplayText) ? $reasonDisplayText : 'Other';
    }

    public function loadFromJsonObject($object)
    {
        if (!empty($object->samples)) {
            foreach ($object->samples as $sample) {
                $sampleCode = $sample->test;
                if (!array_key_exists($sample->test, $this->samplesInformation) && array_key_exists($sample->test, self::$mapRdrSamples)) {
                    $sampleCode = self::$mapRdrSamples[$sample->test]['code'];
                    $centrifugeType = self::$mapRdrSamples[$sample->test]['centrifuge_type'];
                }
                if (!empty($sample->collected)) {
                    $collectedSamples[] = $sampleCode;
                    $collectedTs = $sample->collected;
                }
                if (!empty($sample->processed)) {
                    $processedSamples[] = $sampleCode;
                    $processedTs = $sample->processed;
                    $processedSamplesTs[$sampleCode] = (new \DateTime($sample->processed))->getTimestamp();
                }
                if (!empty($sample->finalized)) {
                    $finalizedSamples[] = $sampleCode;
                    $finalizedTs = $sample->finalized;
                }
            }
        }

        // Update notes field
        $collectedNotes = !empty($object->notes->collected) ? $object->notes->collected : null;
        $processedNotes = !empty($object->notes->processed) ? $object->notes->processed : null;
        $finalizedNotes = !empty($object->notes->finalized) ? $object->notes->finalized : null;

        if (!empty($object->identifier)) {
            foreach ($object->identifier as $identifier) {
                if (preg_match('/tracking-number/i', $identifier->system)) {
                    $trackingNumber = $identifier->value;
                }
                if (preg_match('/kit-id/i', $identifier->system)) {
                    $kitId = $identifier->value;
                }
            }
        }

        // Extract participantId
        preg_match('/^Patient\/(P\d+)$/i', $object->subject, $subject_matches);
        $participantId = $subject_matches[1];

        $this->order['id'] = $object->id;
        $this->order['participant_id'] = $participantId;
        $this->order['order_id'] = $kitId;
        $this->order['rdr_id'] = $object->id;
        $this->order['biobank_id'] = (property_exists($object, 'biobankId')) ? $object->biobankId : null;
        $this->order['type'] = 'kit';
        $this->order['oh_type'] = 'kit';
        $this->order['h_type'] = 'kit';
        $this->order['created_ts'] = $object->created;
        $this->order['processed_ts'] = $processedTs;
        $this->order['collected_ts'] = $collectedTs;
        $this->order['finalized_ts'] = $finalizedTs;
        $this->order['processed_centrifuge_type'] = (!empty($centrifugeType)) ? $centrifugeType : null;
        $this->order['requested_samples'] = null;
        $this->order['collected_samples'] = json_encode(!empty($collectedSamples) ? $collectedSamples : []);
        $this->order['collected_ts'] = !empty($collectedTs) ? $collectedTs : null;
        $this->order['processed_samples'] = json_encode(!empty($processedSamples) ? $processedSamples : []);
        $this->order['processed_samples_ts'] = json_encode(!empty($processedSamplesTs) ? $processedSamplesTs : []);
        $this->order['finalized_samples'] = json_encode(!empty($finalizedSamples) ? $finalizedSamples : []);
        $this->order['finalizedSamples'] = !empty($finalizedSamples) ? join($finalizedSamples, ', ') : '';
        $this->order['finalized_ts'] = !empty($finalizedTs) ? $finalizedTs : null;
        $this->order['collected_notes'] = $collectedNotes;
        $this->order['processed_notes'] = $processedNotes;
        $this->order['finalized_notes'] = $finalizedNotes;
        $this->order['fedex_tracking'] = !empty($trackingNumber) ? $trackingNumber : null;
        $this->order['collected_user_id'] = !empty($object->collectedInfo) ? $object->collectedInfo->author->value : false;
        $this->order['collected_site'] = null;
        $this->order['collected_site_name'] = 'A Quest Site';
        $this->order['collected_site_address'] = !empty($object->collectedInfo->address) ? $object->collectedInfo->address : null;
        $this->order['processed_user_id'] = !empty($object->processedInfo) ? $object->processedInfo->author->value : false;
        $this->order['processed_site'] = null;
        $this->order['processed_site_name'] = 'A Quest Site';
        $this->order['finalized_user_id'] = !empty($object->finalizedInfo) ? $object->finalizedInfo->author->value : false;
        $this->order['finalized_site'] = null;
        $this->order['finalized_site_name'] = 'A Quest Site';
        $this->order['failedToReachRDR'] = false;
        $this->order['orderStatus'] = 'Finalized';
        $this->order['status'] = 'finalized';
        $this->order['biobank_finalized'] = false;

        $this->order['origin'] = $object->origin;

        return $this;
    }

    public function sendOrderToMayo($mayoClientId)
    {
        // Return mock id for mock orders
        if ($this->app->getConfig('ml_mock_order')) {
            return ['status' => 'success', 'mayoId' => $this->app->getConfig('ml_mock_order')];
        }
        $result = ['status' => 'fail'];
        // Set collected time to user local time
        $collectedAt = new \DateTime($this->order['biobank_collected_ts']->format('Y-m-d H:i:s'), new \DateTimeZone($this->app->getUserTimezone()));
        // Check if mayo account number exists
        if (!empty($mayoClientId)) {
            $birthDate = $this->app->getConfig('ml_real_dob') ? $this->participant->dob : $this->participant->getMayolinkDob();
            if ($birthDate) {
                $birthDate = $birthDate->format('Y-m-d');
            }
            $options = [
                'type' => $this->order['type'],
                'biobank_id' => $this->participant->biobankId,
                'first_name' => '*',
                'gender' => $this->participant->gender,
                'birth_date' => $birthDate,
                'order_id' => $this->order['order_id'],
                'collected_at' => $collectedAt->format('c'),
                'mayoClientId' => $mayoClientId,
                'collected_samples' => $this->order['biobank_finalized_samples'],
                'centrifugeType' => $this->order['processed_centrifuge_type'],
                'version' => $this->order['version'],
                'tests' => $this->samplesInformation,
                'salivaTests' => $this->salivaSamplesInformation
            ];
            $mayoOrder = new MayolinkOrder($this->app);
            $mayoId = $mayoOrder->createOrder($options);
            if (!empty($mayoId)) {
                $result['status'] = 'success';
                $result['mayoId'] = $mayoId;
            } else {
                $result['errorMessage'] = 'An error occurred while attempting to send this order. Please try again.';
            }
        } else {
            $result['errorMessage'] = 'Mayo account number is not set for this site. Please contact the administrator.';
        }
        return $result;
    }

    public function createBiobankOrderFinalizeForm($site)
    {
        $disabled = $this->isOrderFormDisabled();
        $formData = $this->getOrderFormData('finalized');
        $samples = $this->getRequestedSamples();
        $formBuilder = $this->app['form.factory']->createBuilder(FormType::class, $formData);
        if (!empty($samples)) {
            $samplesDisabled = $disabled;
            $formBuilder->add("finalized_samples", Type\ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => 'Which samples are being shipped to the All of Us℠ Biobank?',
                'choices' => $samples,
                'required' => false,
                'disabled' => $samplesDisabled
            ]);
        }
        if ($this->order['type'] === 'kit' && empty($site['centrifuge_type'])) {
            $formBuilder->add('processed_centrifuge_type', Type\ChoiceType::class, [
                'label' => 'Centrifuge type',
                'required' => false,
                'choices' => [
                    '-- Select centrifuge type --' => null,
                    'Fixed Angle' => self::FIXED_ANGLE,
                    'Swinging Bucket' => self::SWINGING_BUCKET
                ],
                'multiple' => false
            ]);
        }
        $formBuilder->add("finalized_notes", Type\TextareaType::class, [
            'label' => 'Additional notes on finalization',
            'disabled' => $disabled,
            'required' => false,
            'constraints' => new Constraints\Type('string')
        ]);
        $form = $formBuilder->getForm();
        return $form;
    }

    public function getFinalizedProcessSamples($samples)
    {
        $processSamples = [];
        foreach ($samples as $sample) {
            if (in_array($sample, self::$samplesRequiringProcessing)) {
                array_push($processSamples, $sample);
            }
        }
        return $processSamples;
    }

    public function checkBiobankChanges(&$updateArray, $finalizedTs, $finalizedSamples, $finalizedNotes, $centrifugeType)
    {
        $biobankChanges = [];
        $collectedSamples = !empty($this->get('collected_samples')) ? json_decode($this->get('collected_samples'), true) : [];
        $processedSamples = !empty($this->get('processed_samples')) ? json_decode($this->get('processed_samples'), true) : [];
        $processedSamplesTs = !empty($this->get('processed_samples_ts')) ? json_decode($this->get('processed_samples_ts'), true) : [];
        $collectedSamplesDiff = array_values(array_diff($finalizedSamples, $collectedSamples));
        $finalizedProcessSamples = $this->getFinalizedProcessSamples($finalizedSamples);
        $processedSamplesDiff = array_values(array_diff($finalizedProcessSamples, $processedSamples));
        if (empty($this->get('collected_ts'))) {
            $updateArray['collected_ts'] = $finalizedTs;
            $updateArray['collected_user_id'] = null;
            $biobankChanges['collected'] = [
                'time' => $updateArray['collected_ts']->getTimestamp(),
                'user' => $updateArray['collected_user_id']
            ];
        }
        if (empty($collectedSamples) || !empty($collectedSamplesDiff)) {
            $updateArray['collected_site'] = $this->get('site');
            $updateArray['collected_samples'] = json_encode(array_merge($collectedSamples, $collectedSamplesDiff));
            $biobankChanges['collected']['site'] = $updateArray['collected_site'];
            $biobankChanges['collected']['samples'] = $collectedSamplesDiff;
        }
        if (empty($processedSamplesTs)) {
            $updateArray['processed_ts'] = $finalizedTs;
            $updateArray['processed_user_id'] = null;
            $biobankChanges['processed'] = [
                'time' => $finalizedTs->getTimestamp(),
                'user' => $updateArray['processed_user_id']
            ];
            $finalizedProcessSamplesTs = [];
            foreach ($finalizedProcessSamples as $sample) {
                $finalizedProcessSamplesTs[$sample] = $finalizedTs->getTimestamp();
            }
            $updateArray['processed_site'] = $this->get('site');
            $updateArray['processed_samples'] = json_encode($finalizedProcessSamples);
            $updateArray['processed_samples_ts'] = json_encode($finalizedProcessSamplesTs);
            $biobankChanges['processed']['site'] = $updateArray['processed_site'];
            $biobankChanges['processed']['samples'] = $finalizedProcessSamples;
            $biobankChanges['processed']['samples_ts'] = $finalizedProcessSamplesTs;
        }
        if (!empty($processedSamplesTs) && !empty($processedSamplesDiff)) {
            $totalProcessedSamples = array_merge($processedSamples, $processedSamplesDiff);
            $newProcessedSampleTs = [];
            foreach ($processedSamplesDiff as $sample) {
                $newProcessedSampleTs[$sample] = $finalizedTs->getTimestamp();
            }
            $updateArray['processed_site'] = $this->get('site');
            $updateArray['processed_samples'] = json_encode($totalProcessedSamples);
            $updateArray['processed_samples_ts'] = json_encode(array_merge($newProcessedSampleTs, $processedSamplesTs));
            $biobankChanges['processed']['site'] = $updateArray['processed_site'];
            $biobankChanges['processed']['samples'] = $processedSamplesDiff;
            $biobankChanges['processed']['samples_ts'] = $newProcessedSampleTs;
        }
        if (!empty($centrifugeType)) {
            $updateArray['processed_centrifuge_type'] = $biobankChanges['processed']['centrifuge_type'] = $centrifugeType;
        }
        $updateArray['finalized_ts'] = $finalizedTs;
        $updateArray['finalized_site'] = $this->get('site');
        $updateArray['finalized_user_id'] = null;
        $updateArray['finalized_notes'] = $finalizedNotes;
        $updateArray['finalized_samples'] = json_encode($finalizedSamples);
        $biobankChanges['finalized'] = [
            'time' => $finalizedTs->getTimestamp(),
            'site' => $updateArray['finalized_site'],
            'user' => $updateArray['finalized_user_id'],
            'notes' => $updateArray['finalized_notes'],
            'samples' => $finalizedSamples
        ];
        $updateArray['biobank_finalized'] = 1;
        $updateArray['biobank_changes'] = json_encode($biobankChanges);
    }

    public function getBiobankChangesDetails()
    {
        $samplesInfo = $this->get('type') === 'saliva' ? $this->salivaSamplesInformation : $this->samplesInformation;
        if ($this->get('type') === 'saliva') {
            // Set color to empty string for saliva samples
            foreach (array_keys($samplesInfo) as $key) {
                if (empty($samplesInfo[$key]['color'])) {
                    $samplesInfo[$key]['color'] = '';
                }
            }
        }

        $biobankChanges = !empty($this->get('biobank_changes')) ? json_decode($this->get('biobank_changes'), true) : [];
        if (!empty($biobankChanges['collected']['time'])) {
            $collectedTs = new \DateTime();
            $collectedTs->setTimestamp($biobankChanges['collected']['time']);
            $collectedTs->setTimezone(new \DateTimeZone($this->app->getUserTimezone()));
            $biobankChanges['collected']['time'] = $collectedTs;
        }

        if (!empty($biobankChanges['collected']['samples'])) {
            $sampleDetails = [];
            foreach ($biobankChanges['collected']['samples'] as $sample) {
                $sampleDetails[$sample]['code'] = array_search($sample, $this->getRequestedSamples());
                $sampleDetails[$sample]['color'] = $samplesInfo[$sample]['color'];
            }
            $biobankChanges['collected']['sample_details'] = $sampleDetails;
        }

        if (!empty($biobankChanges['processed']['samples_ts'])) {
            $sampleDetails = [];
            foreach ($biobankChanges['processed']['samples_ts'] as $sample => $time) {
                $sampleDetails[$sample]['code'] = array_search($sample, $this->getRequestedSamples());
                $sampleDetails[$sample]['color'] = $samplesInfo[$sample]['color'];
                $processedTs = new \DateTime();
                $processedTs->setTimestamp($time);
                $processedTs->setTimezone(new \DateTimeZone($this->app->getUserTimezone()));
                $sampleDetails[$sample]['time'] = $processedTs;
            }
            $biobankChanges['processed']['sample_details'] = $sampleDetails;
        }

        if (!empty($biobankChanges['processed']['centrifuge_type'])) {
            $biobankChanges['processed']['centrifuge_type'] = self::$centrifugeType[$biobankChanges['processed']['centrifuge_type']];
        }

        if (!empty($biobankChanges['finalized']['time'])) {
            $collectedTs = new \DateTime();
            $collectedTs->setTimestamp($biobankChanges['finalized']['time']);
            $collectedTs->setTimezone(new \DateTimeZone($this->app->getUserTimezone()));
            $biobankChanges['finalized']['time'] = $collectedTs;
        }

        if (!empty($biobankChanges['finalized']['samples'])) {
            $sampleDetails = [];
            foreach ($biobankChanges['finalized']['samples'] as $sample) {
                $sampleDetails[$sample]['code'] = array_search($sample, $this->getRequestedSamples());
                $sampleDetails[$sample]['color'] = $samplesInfo[$sample]['color'];
            }
            $biobankChanges['finalized']['sample_details'] = $sampleDetails;
        }

        return $biobankChanges;
    }

    public function requireCentrifugeType($samples)
    {
        return !empty(array_intersect($samples, self::$samplesRequiringCentrifugeType)) ? true : false;
    }

    public function canEdit()
    {
        // Allow cohort 1 and 2 participants to edit existing orders even if status is false
        return !$this->getParticipant()->status && !empty($this->order['id']) ? $this->getParticipant()->editExistingOnly : $this->getParticipant()->status;
    }
}
