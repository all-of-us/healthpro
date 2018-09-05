<?php
namespace Pmi\Order;

use Silex\Application;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Validator\Constraints;
use Pmi\Util;

class Order
{
    protected $app;
    protected $order;
    protected $participant;
    public $version = 2;
    const FIXED_ANGLE = 'fixed_angle';
    const SWINGING_BUCKET = 'swinging_bucket';
    const ORDER_CANCEL = 'cancel';
    const ORDER_RESTORE = 'restore';
    const ORDER_ACTIVE = 'active';

    // These labels are a fallback - when displayed, they should be using the
    // sample information below to render a table with more information

    public $samples;

    public $samplesInformation;

    public $salivaSamples;

    public $salivaSamplesInformation;

    public $salivaInstructions;

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
        foreach($this->samplesInformation as $sample => $info) {
            $label = "({$info['number']}) {$info['label']} [{$sample}]";
            $samples[$label] = $sample;
        }
        $this->samples = $samples;

        $this->salivaSamplesInformation = $schema['salivaSamplesInformation'];
        $salivaSamples = [];
        foreach($this->salivaSamplesInformation as $salivaSample => $info) {
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
            if (isset($sampleInformation['icodeSwingingBucket'])){
                // For custom order creation (always display swinging bucket i-test codes)
                if (empty($this->order)) {
                    $sampleId = $sampleInformation['icodeSwingingBucket'];
                } elseif (!empty($this->order) && empty($this->order['type'])) {
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
        $order = $this->app['em']->getRepository('orders')->fetchOneBy([
            'id' => $orderId,
            'participant_id' => $participantId
        ]);
        if (!$order) {
            return;
        }
        $this->order = $order;
        $this->order['expired'] = $this->isOrderExpired();
        $this->participant = $participant;
        $this->version = !empty($order['version']) ? $order['version'] : 1;
        $this->loadOrderHistory();
        $this->order['disabled'] = $this->isOrderDisabled();
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
            // Remove processed samples when not collected
            if ($set === 'collected' && !empty($this->order['processed_samples_ts'])) {
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
                $updateArray["processed_samples"] = json_encode($newProcessedSamples);
                $updateArray["processed_samples_ts"] = json_encode($newProcessedSamplesTs);
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
            }
        }
        if ($set === 'finalized' && $this->order['type'] === 'kit') {
            $updateArray['fedex_tracking'] = $formData['fedex_tracking'];
        }
        return $updateArray;
    }

    public function createOrderForm($set, $formFactory)
    {
        $disabled = $this->isOrderDisabled();

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
            if ($set === 'collected' && $this->order['mayo_id']) {
                $samplesDisabled = true;
            }
            $formBuilder->add("{$set}_samples", Type\ChoiceType::class, [
                'expanded' => true,
                'multiple' => true,
                'label' => $samplesLabel,
                'choices' => $samples,
                'required' => false,
                'disabled' => $samplesDisabled,
                'choice_attr' => function($val) use ($enabledSamples, $set) {
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
            if ($this->app->isDVType()) {
                $sites = $this->app['em']->getRepository('sites')->fetchOneBy([
                    'google_group' => $this->app->getSiteId()
                ]);
                if ($this->order['type'] !== 'saliva' && !empty($enabledSamples) && empty($sites['centrifuge_type'])) {
                    $formBuilder->add('processed_centrifuge_type', Type\ChoiceType::class, [
                        'label' => 'Centrifuge type',
                        'required' => true,
                        'disabled' => $disabled,
                        'choices' => [
                            '-- Select centrifuge type --' => null,
                            'Fixed Angle'=> self::FIXED_ANGLE,
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
        if ($set === 'finalized' && $this->order['type'] === 'kit') {
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
                $identifiers[] =[
                    'system' => 'https://orders.mayomedicallaboratories.com',
                    'value' => $this->order['mayo_id']
                ];
            } else {
                $identifiers[] =[
                    'system' => 'https://orders.mayomedicallaboratories.com',
                    'value' => 'PMITEST-' . $this->order['order_id']
                ];
            }
            $createdUser = $this->getOrderUser($this->order['user_id'], null);
            $createdSite = $this->getOrderSite($this->order['site'], null);
            $collectedUser = $this->getOrderUser($this->order['collected_user_id'], 'collected');
            $collectedSite = $this->getOrderSite($this->order['collected_site'], 'collected');
            $processedUser = $this->getOrderUser($this->order['processed_user_id'], 'processed');
            $processedSite = $this->getOrderSite($this->order['processed_site'], 'processed');
            $finalizedUser = $this->getOrderUser($this->order['finalized_user_id'], 'finalized');
            $finalizedSite = $this->getOrderSite($this->order['finalized_site'], 'finalized');
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

    public function sendToRdr()
    {
        if (!$this->order['finalized_ts']) {
            return false;
        }
        $order = $this->getRdrObject();
        $rdrId = $this->app['pmi.drc.participants']->createOrder($this->participant->id, $order);
        if ($rdrId) {
            $this->app['em']->getRepository('orders')->update(
                $this->order['id'],
                ['rdr_id' => $rdrId]
            );
        }
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

    protected function getOrderUser($userId, $type)
    {
        if ($type) {
            $userId = $this->order["{$type}_user_id"] ? $this->order["{$type}_user_id"] : $this->order['user_id'];
        }
        $user = $this->app['em']->getRepository('users')->fetchOneBy([
            'id' => $userId
        ]);
        return $user['email'];
    }

    protected function getOrderSite($site, $type)
    {
        if ($type) {
            $site = $this->order["{$type}_site"] ? $this->order["{$type}_site"] : $this->order['site'];
        }
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

    public function isOrderDisabled()
    {
        return $this->order['finalized_ts'] || $this->order['expired'] || $this->isOrderCancelled();
    }

    public function isOrderCancelled()
    {
        return $this->order['status'] === self::ORDER_CANCEL;
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

    private function getNumericId()
    {
        $length = 10;
        // Avoid leading 0s
        $id = (string)rand(1,9);
        for ($i = 0; $i < $length - 1; $i++) {
            $id .= (string)rand(0,9);
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
        foreach ($this->getRequestedSamples() as $key => $value) {
            $sample = ['code' => $key];
            if (!empty($this->order['collected_ts']) && in_array($value, json_decode($this->order['collected_samples']))) {
                $sample['collected_ts'] = $this->order['collected_ts'];
            }
            if (!empty($this->order['finalized_ts']) && in_array($value, json_decode($this->order['finalized_samples']))) {
                $sample['finalized_ts'] = $this->order['finalized_ts'];
            }
            if (!empty($this->order['processed_samples_ts']) && in_array($value, json_decode($this->order['processed_samples']))) {
                $processedSamplesTs = json_decode($this->order['processed_samples_ts'], true);
                if (!empty($processedSamplesTs[$value])) {
                    $processedTs = new \DateTime();
                    $processedTs->setTimestamp($processedSamplesTs[$value]);
                    $processedTs->setTimezone(new \DateTimeZone($this->app->getUserTimezone()));
                    $sample['processed_ts'] = $processedTs;
                }
            }
            if (in_array($value, self::$samplesRequiringProcessing)) {
                $sample['process'] = true;
            }
            $samples[] = $sample;
        }
        return $samples;
    }

    public function getOrderModifyForm($type)
    {
        $orderModifyForm = $this->app['form.factory']->createBuilder(Type\FormType::class, null);
        $orderModifyForm->add('reason', Type\TextareaType::class, [
            'label' => 'Reason',
            'required' => true,
            'constraints' => [
                new Constraints\NotBlank(),
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
                    'placeholder' => 'Type the word "CANCEL" to confirm'
                ]
            ]);
        }
        return $orderModifyForm->getForm();
    }

    public function loadOrderHistory()
    {
        $this->order['status'] = self::ORDER_ACTIVE;
        $orderHistory = $this->app['em']->getRepository('orders_history')->fetchBy(
            ['order_id' => $this->order['id']],
            ['created_ts' => 'DESC', 'id' => 'DESC'],
            [1]
        );
        if (!empty($orderHistory)) {
            $this->order['status'] = $orderHistory[0]['type'];
            $this->order['history']['user_id'] = $orderHistory[0]['user_id'];
            $this->order['history']['site'] = $orderHistory[0]['site'];
            $this->order['history']['time'] = $orderHistory[0]['created_ts'];
        }
    }

    public function getParticipantOrdersWithHistory($participantId)
    {
        $ordersQuery = "
            SELECT orders.*, orders_history_tmp.*
                FROM orders
                LEFT JOIN
                (SELECT oh1.order_id AS oh_order_id,
                    oh1.user_id AS oh_user_id,
                    oh1.site AS oh_site,
                    oh1.type AS oh_type,
                    oh1.created_ts AS oh_created_ts
                    FROM orders_history AS oh1
                    LEFT JOIN orders_history AS oh2 ON oh1.order_id = oh2.order_id
                    AND oh1.created_ts < oh2.created_ts
                    WHERE oh2.order_id IS NULL 
                ) AS orders_history_tmp ON (orders.id = orders_history_tmp.oh_order_id)
                WHERE orders.participant_id = ?
                ORDER BY orders.id DESC
            ";
        return $this->app['db']->fetchAll($ordersQuery, [$participantId]);
    }
}
