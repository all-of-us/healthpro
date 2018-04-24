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

    // These labels are a fallback - when displayed, they should be using the
    // sample information below to render a table with more information

    public $samples;

    public $samplesInformation;

    public $salivaSamples;

    public $salivaSamplesInformation;

    public static $samplesRequiringProcessing = ['1SST8', '1PST8', '1SS08', '1PS08', '1SAL'];

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
        '1SAL' => 'sal'
    ];

    public static $nonBloodSamples = ['1UR10', '1SAL'];

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
            throw new \Pmi\Evaluation\MissingSchemaException();
        }
        $schema = json_decode(file_get_contents($file), true);
        if (!is_array($schema) && !empty($schema)) {
            throw new \Pmi\Evaluation\InvalidSchemaException();
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
        }
        $this->salivaSamples = $salivaSamples;
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
        //Return collect step if collected_ts is set and mayo_id is empty
        if ($this->order["collected_ts"] && !$this->order["mayo_id"]) {
            return 'collect';
        }
        $columns = [
            'printLabels' => 'printed',
            'collect' => 'collected',
            'printRequisition' => 'collected',
            'process' => 'processed',
            'finalize' => 'finalized'           
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
            'printRequisition' => 'collected',
            'process' => 'processed',
            'finalize' => 'finalized'
        ];
        if ($this->order['type'] === 'kit') {
            unset($columns['printLabels']);
            unset($columns['printRequisition']);
        }
        $steps = [];
        foreach ($columns as $name => $column) {
            $steps[] = $name;
            if ($column === 'collected' && $this->order['type'] !== 'kit') {
                $condition = $this->order["{$column}_ts"] && $this->order["mayo_id"];
            } else {
                $condition = $this->order["{$column}_ts"];
            }
            if (!$condition) {
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
            if ($hasSampleArray) {
                $updateArray["{$set}_samples"] = json_encode(array_values($formData["{$set}_samples"]));
            } else {
                $updateArray["{$set}_samples"] = json_encode([]);
            }
            if ($set == 'processed') {
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
        $disabled = $this->order['finalized_ts'] || $this->order['expired'] ? true : false;

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
        $nonBloodSample = count($samples) === 1 && $this->isNonBloodSample($samples);
        if ($set == 'collected' && !$nonBloodSample) {
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

    public function isNonBloodSample($samples)
    {
        foreach ($samples as $sampleCode) {
            if (in_array($sampleCode, self::$nonBloodSamples)) {
                return true;
            }
        }
    }
}
