<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\OrderRepository")
 * @ORM\Table(name="`orders`")
 */
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

    private $params;
    private $samples;
    private $samplesInformation;
    private $salivaSamples;
    private $salivaSamplesInformation;
    private $salivaInstructions;
    private $currentVersion;

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

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User", cascade={"persist", "remove"})
     */
    private $user;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $site;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $participantId;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $rdrId;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $biobankId;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdTs;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $orderId;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $mayoId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $requestedSamples;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $printedTs;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User", cascade={"persist", "remove"})
     */
    private $collectedUser;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $collectedSite;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $collectedTs;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $collectedSamples;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $collectedNotes;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User", cascade={"persist", "remove"})
     */
    private $processedUser;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $processedSite;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $processedTs;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $processedSamples;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $processedSamplesTs;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $processedCentrifugeType;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $processedNotes;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User", cascade={"persist", "remove"})
     */
    private $finalizedUser;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $finalizedTs;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $finalizedSamples;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $finalizedNotes;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $fedexTracking;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $version;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $biobankFinalized = false;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $biobankChanges;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\OrderHistory", cascade={"persist", "remove"})
     */
    private $history;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $finalizedSite;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getSite(): ?string
    {
        return $this->site;
    }

    public function setSite(string $site): self
    {
        $this->site = $site;

        return $this;
    }

    public function getParticipantId(): ?string
    {
        return $this->participantId;
    }

    public function setParticipantId(string $participantId): self
    {
        $this->participantId = $participantId;

        return $this;
    }

    public function getRdrId(): ?string
    {
        return $this->rdrId;
    }

    public function setRdrId(string $rdrId): self
    {
        $this->rdrId = $rdrId;

        return $this;
    }

    public function getBiobankId(): ?string
    {
        return $this->biobankId;
    }

    public function setBiobankId(string $biobankId): self
    {
        $this->biobankId = $biobankId;

        return $this;
    }

    public function getCreatedTs(): ?\DateTimeInterface
    {
        return $this->createdTs;
    }

    public function setCreatedTs(\DateTimeInterface $createdTs): self
    {
        $this->createdTs = $createdTs;

        return $this;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): self
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function getMayoId(): ?string
    {
        return $this->mayoId;
    }

    public function setMayoId(?string $mayoId): self
    {
        $this->mayoId = $mayoId;

        return $this;
    }

    public function getRequestedSamples(): ?string
    {
        return $this->requestedSamples;
    }

    public function setRequestedSamples(?string $requestedSamples): self
    {
        $this->requestedSamples = $requestedSamples;

        return $this;
    }

    public function getPrintedTs(): ?\DateTimeInterface
    {
        return $this->printedTs;
    }

    public function setPrintedTs(?\DateTimeInterface $printedTs): self
    {
        $this->printedTs = $printedTs;

        return $this;
    }

    public function getCollectedUser(): ?User
    {
        return $this->collectedUser;
    }

    public function setCollectedUser(?User $collectedUser): self
    {
        $this->collectedUser = $collectedUser;

        return $this;
    }

    public function getCollectedSite(): ?string
    {
        return $this->collectedSite;
    }

    public function setCollectedSite(?string $collectedSite): self
    {
        $this->collectedSite = $collectedSite;

        return $this;
    }

    public function getCollectedTs(): ?\DateTimeInterface
    {
        return $this->collectedTs;
    }

    public function setCollectedTs(?\DateTimeInterface $collectedTs): self
    {
        $this->collectedTs = $collectedTs;

        return $this;
    }

    public function getCollectedSamples(): ?string
    {
        return $this->collectedSamples;
    }

    public function setCollectedSamples(?string $collectedSamples): self
    {
        $this->collectedSamples = $collectedSamples;

        return $this;
    }

    public function getCollectedNotes(): ?string
    {
        return $this->collectedNotes;
    }

    public function setCollectedNotes(?string $collectedNotes): self
    {
        $this->collectedNotes = $collectedNotes;

        return $this;
    }

    public function getProcessedUser(): ?User
    {
        return $this->processedUser;
    }

    public function setProcessedUser(?User $processedUser): self
    {
        $this->processedUser = $processedUser;

        return $this;
    }

    public function getProcessedSite(): ?string
    {
        return $this->processedSite;
    }

    public function setProcessedSite(?string $processedSite): self
    {
        $this->processedSite = $processedSite;

        return $this;
    }

    public function getProcessedTs(): ?\DateTimeInterface
    {
        return $this->processedTs;
    }

    public function setProcessedTs(?\DateTimeInterface $processedTs): self
    {
        $this->processedTs = $processedTs;

        return $this;
    }

    public function getProcessedSamples(): ?string
    {
        return $this->processedSamples;
    }

    public function setProcessedSamples(?string $processedSamples): self
    {
        $this->processedSamples = $processedSamples;

        return $this;
    }

    public function getProcessedSamplesTs(): ?string
    {
        return $this->processedSamplesTs;
    }

    public function setProcessedSamplesTs(?string $processedSamplesTs): self
    {
        $this->processedSamplesTs = $processedSamplesTs;

        return $this;
    }

    public function getProcessedCentrifugeType(): ?string
    {
        return $this->processedCentrifugeType;
    }

    public function setProcessedCentrifugeType(?string $processedCentrifugeType): self
    {
        $this->processedCentrifugeType = $processedCentrifugeType;

        return $this;
    }

    public function getProcessedNotes(): ?string
    {
        return $this->processedNotes;
    }

    public function setProcessedNotes(?string $processedNotes): self
    {
        $this->processedNotes = $processedNotes;

        return $this;
    }

    public function getFinalizedUser(): ?User
    {
        return $this->finalizedUser;
    }

    public function setFinalizedUser(?User $finalizedUser): self
    {
        $this->finalizedUser = $finalizedUser;

        return $this;
    }

    public function getFinalizedTs(): ?\DateTimeInterface
    {
        return $this->finalizedTs;
    }

    public function setFinalizedTs(?\DateTimeInterface $finalizedTs): self
    {
        $this->finalizedTs = $finalizedTs;

        return $this;
    }

    public function getFinalizedSamples(): ?string
    {
        return $this->finalizedSamples;
    }

    public function setFinalizedSamples(string $finalizedSamples): self
    {
        $this->finalizedSamples = $finalizedSamples;

        return $this;
    }

    public function getFinalizedNotes(): ?string
    {
        return $this->finalizedNotes;
    }

    public function setFinalizedNotes(?string $finalizedNotes): self
    {
        $this->finalizedNotes = $finalizedNotes;

        return $this;
    }

    public function getFedexTracking(): ?string
    {
        return $this->fedexTracking;
    }

    public function setFedexTracking(?string $fedexTracking): self
    {
        $this->fedexTracking = $fedexTracking;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getBiobankFinalized(): ?bool
    {
        return $this->biobankFinalized;
    }

    public function setBiobankFinalized(?bool $biobankFinalized): self
    {
        $this->biobankFinalized = $biobankFinalized;

        return $this;
    }

    public function getBiobankChanges(): ?string
    {
        return $this->biobankChanges;
    }

    public function setBiobankChanges(?string $biobankChanges): self
    {
        $this->biobankChanges = $biobankChanges;

        return $this;
    }

    public function getHistory(): ?OrderHistory
    {
        return $this->history;
    }

    public function setHistoryId(?OrderHistory $history): self
    {
        $this->history = $history;

        return $this;
    }

    public function getFinalizedSite(): ?string
    {
        return $this->finalizedSite;
    }

    public function setFinalizedSite(?string $finalizedSite): self
    {
        $this->finalizedSite = $finalizedSite;

        return $this;
    }

    public function getSamples()
    {
        return $this->samples;
    }

    public function getSamplesInformation()
    {
        return $this->samplesInformation;
    }

    public function getSalivaSamples()
    {
        return $this->salivaSamples;
    }

    public function getSalivaSamplesInformation()
    {
        return $this->salivaSamplesInformation;
    }

    public function getSalivaInstructions()
    {
        return $this->salivaInstructions;
    }

    public function getCurrentVersion()
    {
        return $this->currentVersion;
    }

    public function loadSamplesSchema($params = [])
    {
        $this->currentVersion = $this->getVersion();
        if (!empty($params['order_samples_version'])) {
            $this->currentVersion = $params['order_samples_version'];
        }
        $this->params = $params;
        $file = __DIR__ . "/../../../src/Pmi/Order/versions/{$this->currentVersion}.json";
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
                if (empty($this->getType()) || $this->getType() === 'diversion') {
                    if ($this->getProcessedCentrifugeType() === self::SWINGING_BUCKET) {
                        $sampleId = $sampleInformation['icodeSwingingBucket'];
                    } elseif ($this->getProcessedCentrifugeType() === self::FIXED_ANGLE) {
                        $sampleId = $sampleInformation['icodeFixedAngle'];
                    }
                }
            }
            $this->samplesInformation[$sample]['sampleId'] = $sampleId;
        }
    }

    public function getRdrObject()
    {
        $obj = new \StdClass();
        $obj->subject = 'Patient/' . $this->getParticipantId();
        $identifiers = [];
        $identifiers[] = [
            'system' => 'https://www.pmi-ops.org',
            'value' => $this->getOrderId()
        ];
        if ($this->getType() === 'kit') {
            $identifiers[] = [
                'system' => 'https://orders.mayomedicallaboratories.com/kit-id',
                'value' => $this->getOrderId()
            ];
            if (!empty($this->getFedexTracking())) {
                $identifiers[] = [
                    'system' => 'https://orders.mayomedicallaboratories.com/tracking-number',
                    'value' => $this->getFedexTracking()
                ];
            }
        }
        if (empty($this->params['ml_mock_order']) && $this->getMayoId() != 'pmitest') {
            $identifiers[] = [
                'system' => 'https://orders.mayomedicallaboratories.com',
                'value' => $this->getMayoId()
            ];
        } else {
            $identifiers[] = [
                'system' => 'https://orders.mayomedicallaboratories.com',
                'value' => 'PMITEST-' . $this->getOrderId()
            ];
        }
        $createdUser = $this->getOrderUser($this->getUser());
        $createdSite = $this->getOrderSite($this->getSite());
        $collectedUser = $this->getOrderUser($this->getCollectedUser());
        $collectedSite = $this->getOrderSite($this->getCollectedSite());
        $processedUser = $this->getOrderUser($this->getProcessedUser());
        $processedSite = $this->getOrderSite($this->getProcessedSite());
        $finalizedUser = $this->getOrderUser($this->getFinalizedUser());
        $finalizedSite = $this->getOrderSite($this->getFinalizedSite());
        $obj->createdInfo = $this->getOrderUserSiteData($createdUser, $createdSite);
        $obj->collectedInfo = $this->getOrderUserSiteData($collectedUser, $collectedSite);
        $obj->processedInfo = $this->getOrderUserSiteData($processedUser, $processedSite);
        $obj->finalizedInfo = $this->getOrderUserSiteData($finalizedUser, $finalizedSite);
        $obj->identifier = $identifiers;
        $created = clone $this->getCreatedTs();
        $created->setTimezone(new \DateTimeZone('UTC'));
        $obj->created = $created->format('Y-m-d\TH:i:s\Z');
        $obj->samples = $this->getRdrSamples();
        $notes = [];
        foreach (['Collected', 'Processed', 'Finalized'] as $step) {
            if ($this->{'get' . $step . 'Notes'}()) {
                $notes[$step] = $this->{'get' . $step . 'Notes'}();
            }
        }
        if (!empty($notes)) {
            $obj->notes = $notes;
        }
        return $obj;
    }

    protected function getOrderUser($user)
    {
        if ($this->getBiobankFinalized() && empty($user)) {
            return 'BiobankUser';
        }
        $user = $user ?: $this->getUser();
        return $user->getEmail() ?? '';
    }

    protected function getOrderSite($site)
    {
        $site = $site ?: $this->getSite();
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
        $samples = json_decode($this->{'get' . $set . 'Samples'}());
        if (!is_array($samples) || !in_array($sample, $samples)) {
            return false;
        }
        if ($set == 'Processed') {
            $processedSampleTimes = json_decode($this->getProcessedSamplesTs(), true);
            if (!empty($processedSampleTimes[$sample])) {
                try {
                    $time = new \DateTime();
                    $time->setTimestamp($processedSampleTimes[$sample]);
                    return $time->format('Y-m-d\TH:i:s\Z');
                } catch (\Exception $e) {
                }
            }
        } else {
            if ($this->{'get' . $set . 'Ts'}()) {
                $time = clone $this->{'get' . $set . 'Ts'}();
                $time->setTimezone(new \DateTimeZone('UTC'));
                return $time->format('Y-m-d\TH:i:s\Z');
            }
        }
    }

    protected function getRdrSamples()
    {
        $samples = [];
        foreach ($this->getModifiedRequestedSamples() as $description => $test) {
            // Convert new samples
            $rdrTest = $test;
            if ($test == '1SS08') {
                $rdrTest = $this->getProcessedCentrifugeType() == self::FIXED_ANGLE ? '2SST8' : '1SST8';
            }
            if ($test == '1PS08') {
                $rdrTest = $this->getProcessedCentrifugeType() == self::FIXED_ANGLE ? '2PST8' : '1PST8';
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

    protected function getModifiedRequestedSamples()
    {
        if ($this->getType() == 'saliva') {
            return $this->salivaSamples;
        }
        if ($this->getRequestedSamples() &&
            ($requestedArray = json_decode($this->getRequestedSamples())) &&
            is_array($requestedArray) &&
            count($requestedArray) > 0
        ) {
            return array_intersect($this->samples, $requestedArray);
        } else {
            return $this->samples;
        }
    }

    public function getStatus()
    {
        $history = $this->getHistory();
        if (!empty($history)) {
            return !empty($history->getType()) ? $history->getType() : self::ORDER_ACTIVE;
        }
    }

    public function isOrderExpired()
    {
        return empty($this->getFinalizedTs()) && empty($this->getVersion());
    }

    // Finalized form is only disabled when rdr_id is set
    public function isOrderDisabled()
    {
        return ($this->getRdrId() || $this->isOrderExpired()|| $this->isOrderCancelled()) && $this->getStatus() !== 'unlock';
    }

    // Except finalize form all forms are disabled when finalized_ts is set
    public function isOrderFormDisabled()
    {
        return ($this->getFinalizedTs() || $this->isOrderExpired() || $this->isOrderCancelled()) && $this->getStatus() !== 'unlock';
    }

    public function isOrderCancelled()
    {
        return $this->getStatus() === self::ORDER_CANCEL;
    }

    public function isOrderUnlocked()
    {
        return $this->getStatus()=== self::ORDER_UNLOCK;
    }

    public function isOrderFailedToReachRdr()
    {
        return !empty($this->getFinalizedTs()) && !empty($this->getMayoId()) && empty($this->getRdrId());
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
        return !$this->isOrderExpired() && !empty($this->getRdrId()) && !$this->isOrderUnlocked() && !$this->isOrderCancelled();
    }

    public function getCurrentStep()
    {
        $columns = [
            'print_labels' => 'Printed',
            'collect' => 'Collected',
            'process' => 'Processed',
            'finalize' => 'Finalized',
            'print_requisition' => 'Finalized'
        ];
        if ($this->getType() === 'kit') {
            unset($columns['print_labels']);
            unset($columns['print_requisition']);
        }
        $step = 'finalize';
        foreach ($columns as $name => $column) {
            if (!$this->{'get' . $column . 'Ts'}()) {
                $step = $name;
                break;
            }
        }
        // For canceled orders set print labels step to collect
        if ($this->isOrderCancelled() && $step === 'print_labels') {
            return 'collect';
        }
        return $step;
    }

    public function getAvailableSteps()
    {
        $columns = [
            'print_labels' => 'Printed',
            'collect' => 'Collected',
            'process' => 'Processed',
            'finalize' => 'Finalized',
            'print_requisition' => 'Finalized'
        ];
        if ($this->getType() === 'kit') {
            unset($columns['print_labels']);
            unset($columns['print_requisition']);
        }
        $steps = [];
        foreach ($columns as $name => $column) {
            $steps[] = $name;
            if (!$this->{'get' . $column . 'Ts'}()) {
                break;
            }
        }
        // For canceled orders include collect in available steps if not exists
        if ($this->isOrderCancelled() && !in_array('collect', $steps)) {
            $steps[] = 'collect';
        }
        return $steps;
    }

    public function getWarnings()
    {
        $warnings = [];
        if ($this->getType() !== 'saliva' && !empty($this->getCollectedTs()) && !empty($this->getProcessedSamplesTs())) {
            $collectedTs = clone $this->getCollectedTs();
            $processedSamples = json_decode($this->getProcessedSamples(), true);
            $processedSamplesTs = json_decode($this->getProcessedSamplesTs(), true);
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
        if (!empty($this->getCollectedTs()) && !empty($this->getProcessedSamplesTs())) {
            $collectedTs = clone $this->getCollectedTs();
            $processedSamples = json_decode($this->getProcessedSamples(), true);
            $processedSamplesTs = json_decode($this->getProcessedSamplesTs(), true);
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
}
