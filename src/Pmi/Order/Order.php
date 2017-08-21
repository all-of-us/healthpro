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

    // These labels are a fallback - when displayed, they should be using the
    // sample information below to render a table with more information
    public static $samples = [
        '(1) 8 mL SST [1SST8]' => '1SST8',
        '(2) 8 mL PST [1PST8]' => '1PST8',
        '(3) 4 mL Na-Hep [1HEP4]' => '1HEP4',
        '(4) 4 mL EDTA [1ED04]' => '1ED04',
        '(5) 1st 10 mL EDTA [1ED10]' => '1ED10',
        '(6) 2nd 10 mL EDTA [2ED10]' => '2ED10',
        '(7) Urine 10 mL [1UR10]' => '1UR10'
    ];

    public static $samplesInformation = [
        '1SST8' => [
            'number' => 1,
            'label' => '8 mL SST',
            'color' => 'Red and gray'
        ],
        '1PST8' => [
            'number' => 2,
            'label' => '8 mL PST',
            'color' => 'Green and gray'
        ],
        '1HEP4' => [
            'number' => 3,
            'label' => '4 mL Na-Hep',
            'color' => 'Green'
        ],
        '1ED04' => [
            'number' => 4,
            'label' => '4 mL EDTA',
            'color' => 'Lavender'
        ],
        '1ED10' => [
            'number' => 5,
            'label' => '1st 10 mL EDTA',
            'color' => 'Lavender'
        ],
        '2ED10' => [
            'number' => 6,
            'label' => '2nd 10 mL EDTA',
            'color' => 'Lavender'
        ],
        '1UR10' => [
            'number' => 7,
            'label' => 'Urine 10 mL',
            'color' => 'Yellow'
        ]
    ];

    public static $salivaSamples = [
        'Saliva [1SAL]' => '1SAL'
    ];

    public static $samplesRequiringProcessing = ['1SST8', '1PST8', '1SAL'];

    public static $identifierLabel = [
        'name' => 'name',
        'dob' => 'date of birth',
        'phone' => 'phone number',
        'address' => 'street address',
        'email' => 'email address'
    ];

    public function loadOrder($participantId, $orderId, Application $app)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            return;
        }
        $order = $app['em']->getRepository('orders')->fetchOneBy([
            'id' => $orderId,
            'participant_id' => $participantId
        ]);
        if (!$order) {
            return;
        }
        $this->app = $app;
        $this->order = $order;
        $this->participant = $participant;
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
            'print' => 'printed',
            'collect' => 'collected',
            'process' => 'processed',
            'finalize' => 'finalized'
        ];
        if ($this->order['type'] === 'kit') {
            unset($columns['print']);
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
            'print' => 'printed',
            'collect' => 'collected',
            'process' => 'processed',
            'finalize' => 'finalized'
        ];
        if ($this->order['type'] === 'kit') {
            unset($columns['print']);
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
        }
        if ($set === 'finalized' && $this->order['type'] === 'kit') {
            $updateArray['fedex_tracking'] = $formData['fedex_tracking'];
        }
        return $updateArray;
    }

    public function createOrderForm($set, $formFactory)
    {
        $disabled = $this->order['finalized_ts'] ? true : false;

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
        $enabledSamples = $this->getEnabledSamples($set);
        $formBuilder = $formFactory->createBuilder(FormType::class, $formData);
        $constraintDateTime = new \DateTime('+5 minutes'); // add buffer for time skew
        if ($set != 'processed') {
            $formBuilder->add("{$set}_ts", Type\DateTimeType::class, [
                'label' => $tsLabel,
                'widget' => 'single_text',
                'format' => 'M/d/yyyy h:mm a',
                'required' => false,
                'disabled' => $disabled,
                'view_timezone' => $this->app->getUserTimezone(),
                'model_timezone' => 'UTC',
                'constraints' => [
                    new Constraints\LessThanOrEqual([
                        'value' => $constraintDateTime,
                        'message' => 'Timestamp cannot be in the future'
                    ])
                ]
            ]);
        }
        $formBuilder->add("{$set}_samples", Type\ChoiceType::class, [
            'expanded' => true,
            'multiple' => true,
            'label' => $samplesLabel,
            'choices' => $samples,
            'required' => false,
            'disabled' => $disabled,
            'choice_attr' => function($val, $key, $index) use ($enabledSamples) {
                if (in_array($val, $enabledSamples)) {
                    return [];
                } else {
                    return ['disabled' => true, 'class' => 'sample-disabled'];
                }
            }
        ]);
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
        }
        if ($set === 'finalized' && $this->order['type'] === 'kit') {
            $formBuilder->add('fedex_tracking', Type\RepeatedType::class, [
                'type' => Type\TextType::class,
                'disabled' => $disabled,
                'invalid_message' => 'FedEx tracking numbers must match.',
                'first_options' => [
                    'label' => 'FedEx tracking number'
                ],
                'second_options' => [
                    'label' => 'Verify FedEx tracking number',
                ],
                'required' => false,
                'error_mapping' => [
                    '.' => 'second' // target the second (repeated) field for non-matching error
                ],
                'constraints' => [
                    new Constraints\Regex([
                        'pattern' => '/^\d{12,14}$/',
                        'message' => 'FedEx tracking numbers must be a string of 12-14 digits'
                    ])
                ]
            ]);
        }
        $formBuilder->add("{$set}_notes", Type\TextareaType::class, [
            'label' => $notesLabel,
            'disabled' => $disabled,
            'required' => false
        ]);
        $form = $formBuilder->getForm();
        return $form;
    }

    public function getRdrObject($order = null, $app = null)
    {
        if ($order) {
            $this->order = $order;
        }
        if ($app) {
            $this->app = $app;
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
            $identifiers[] = [
                'system' => 'https://orders.mayomedicallaboratories.com/tracking-number',
                'value' => $this->order['fedex_tracking']
            ];
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
            $sample = [
                'test' => $test,
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
        }
        if ($set === 'finalized' && $this->order['type'] === 'kit') {
            $formData['fedex_tracking'] = $this->order['fedex_tracking'];
        }
        return $formData;
    }

    protected function getRequestedSamples()
    {
        if ($this->order['type'] == 'saliva') {
            return self::$salivaSamples;
        }
        if ($this->order['requested_samples'] &&
            ($requestedArray = json_decode($this->order['requested_samples'])) &&
            is_array($requestedArray) &&
            count($requestedArray) > 0
        ) {
            return array_intersect(self::$samples, $requestedArray);
        } else {
            return self::$samples;
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
}
