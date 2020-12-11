<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class OrderService
{
    const FIXED_ANGLE = 'fixed_angle';
    const SWINGING_BUCKET = 'swinging_bucket';
    const ORDER_ACTIVE = 'active';
    const ORDER_CANCEL = 'cancel';
    const ORDER_RESTORE = 'restore';
    const ORDER_UNLOCK = 'unlock';
    const ORDER_EDIT = 'edit';
    const ORDER_REVERT = 'revert';

    protected $em;
    protected $session;
    protected $loggerService;
    protected $userService;
    protected $rdrApiService;
    protected $params;
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

    public function __construct(
        EntityManagerInterface $em,
        SessionInterface $session,
        LoggerService $loggerService,
        UserService $userService,
        RdrApiService $rdrApiService,
        ParameterBagInterface $params
    ) {
        $this->em = $em;
        $this->session = $session;
        $this->loggerService = $loggerService;
        $this->userService = $userService;
        $this->rdrApiService = $rdrApiService;
        $this->params = $params;
        if ($params->has('order_samples_version') && !empty($params->get('order_samples_version'))) {
            $this->version = $params->get('order_samples_version');
        }
        $this->loadSamplesSchema();
    }

    public function loadSamplesSchema()
    {
        $file = __DIR__ . "/../../../src/Pmi/Order/versions/{$this->version}.json";
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
                } elseif (!empty($this->order) && (empty($this->order->getType()) || $this->order->getType() === 'diversion')) {
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

    public function getRdrObject($order = null)
    {
        if ($order) {
            $this->order = $order;
        }
        $obj = new \StdClass();
        $obj->subject = 'Patient/' . $this->order->getParticipantId();
        $identifiers = [];
        $identifiers[] = [
            'system' => 'https://www.pmi-ops.org',
            'value' => $this->order->getOrderId()
        ];
        if ($this->order->getType() === 'kit') {
            $identifiers[] = [
                'system' => 'https://orders.mayomedicallaboratories.com/kit-id',
                'value' => $this->order->getOrderId()
            ];
            if (!empty($this->order->getFedexTracking())) {
                $identifiers[] = [
                    'system' => 'https://orders.mayomedicallaboratories.com/tracking-number',
                    'value' => $this->order->getFedexTracking()
                ];
            }
        }
        if (!$this->params->has('ml_mock_order') && $this->order->getMayoId() != 'pmitest') {
            $identifiers[] = [
                'system' => 'https://orders.mayomedicallaboratories.com',
                'value' => $this->order->getMayoId()
            ];
        } else {
            $identifiers[] = [
                'system' => 'https://orders.mayomedicallaboratories.com',
                'value' => 'PMITEST-' . $this->order->getOrderId()
            ];
        }
        $createdUser = $this->getOrderUser($this->order->getUserId());
        $createdSite = $this->getOrderSite($this->order->getSite());
        $collectedUser = $this->getOrderUser($this->order->getCollectedUserId());
        $collectedSite = $this->getOrderSite($this->order->getCollectedSite());
        $processedUser = $this->getOrderUser($this->order->getProcessedUserId());
        $processedSite = $this->getOrderSite($this->order->getProcessedSite());
        $finalizedUser = $this->getOrderUser($this->order->getFinalizedUserId());
        $finalizedSite = $this->getOrderSite($this->order->getFinalizedSite());
        $obj->createdInfo = $this->getOrderUserSiteData($createdUser, $createdSite);
        $obj->collectedInfo = $this->getOrderUserSiteData($collectedUser, $collectedSite);
        $obj->processedInfo = $this->getOrderUserSiteData($processedUser, $processedSite);
        $obj->finalizedInfo = $this->getOrderUserSiteData($finalizedUser, $finalizedSite);
        $obj->identifier = $identifiers;
        $created = clone $this->order->getCreatedTs();
        $created->setTimezone(new \DateTimeZone('UTC'));
        $obj->created = $created->format('Y-m-d\TH:i:s\Z');
        $obj->samples = $this->getRdrSamples();
        $notes = [];
        foreach (['Collected', 'Processed', 'Finalized'] as $step) {
            if ($this->order->{'get' . $step . 'Notes'}()) {
                $notes[$step] = $this->order->{'get' . $step . 'Notes'}();
            }
        }
        if (!empty($notes)) {
            $obj->notes = $notes;
        }
        return $obj;
    }

    protected function getOrderUser($userId)
    {
        if ($this->order->getBiobankFinalized() && empty($userId)) {
            return 'BiobankUser';
        }
        $userId = $userId ?: $this->order->getUserId();
        $user = $this->em->getRepository(User::class)->find($userId);
        return $user->getEmail() ?? '';
    }

    protected function getOrderSite($site)
    {
        $site = $site ?: $this->order->getSite();
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

    protected function getSampleTime($set, $sample)
    {
        $samples = json_decode($this->order->{'get' . $set . 'Samples'}());
        if (!is_array($samples) || !in_array($sample, $samples)) {
            return false;
        }
        if ($set == 'Processed') {
            $processedSampleTimes = json_decode($this->order->getProcessedSamplesTs(), true);
            if (!empty($processedSampleTimes[$sample])) {
                try {
                    $time = new \DateTime();
                    $time->setTimestamp($processedSampleTimes[$sample]);
                    return $time->format('Y-m-d\TH:i:s\Z');
                } catch (\Exception $e) {
                }
            }
        } else {
            if ($this->order->{'get' . $set . 'Ts'}()) {
                $time = clone $this->order->{'get' . $set . 'Ts'}();
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
                $rdrTest = $this->order->getProcessedCentrifugeType() == self::FIXED_ANGLE ? '2SST8' : '1SST8';
            }
            if ($test == '1PS08') {
                $rdrTest = $this->order->getProcessedCentrifugeType() == self::FIXED_ANGLE ? '2PST8' : '1PST8';
            }
            $sample = [
                'test' => $rdrTest,
                'description' => $description,
                'processingRequired' => in_array($test, self::$samplesRequiringProcessing)
            ];
            if ($collected = $this->getSampleTime('Collected', $test)) {
                $sample['collected'] = $collected;
            }
            if ($sample['processingRequired']) {
                $processed = $this->getSampleTime('Processed', $test);
                if ($processed) {
                    $sample['processed'] = $processed;
                }
            }
            if ($finalized = $this->getSampleTime('Finalized', $test)) {
                $sample['finalized'] = $finalized;
            }
            $samples[] = $sample;
        }
        return $samples;
    }

    protected function getRequestedSamples()
    {
        if ($this->order->getType() == 'saliva') {
            return $this->salivaSamples;
        }
        if ($this->order->getRequestedSamples() &&
            ($requestedArray = json_decode($this->order->getRequestedSamples())) &&
            is_array($requestedArray) &&
            count($requestedArray) > 0
        ) {
            return array_intersect($this->samples, $requestedArray);
        } else {
            return $this->samples;
        }
    }

    public function createOrder($participantId, $order)
    {
        try {
            $response = $this->rdrApiService->post("rdr/v1/Participant/{$participantId}/BiobankOrder", $order);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result->id;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    public function getOrder($participantId, $orderId)
    {
        try {
            $response = $this->rdrApiService->get("rdr/v1/Participant/{$participantId}/BiobankOrder/{$orderId}");
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    public function getLastError()
    {
        return $this->rdrApiService->getLastError();
    }

    public function getLastErrorCode()
    {
        return $this->rdrApiService->getLastErrorCode();
    }
}