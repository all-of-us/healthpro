<?php

namespace App\Entity;

use App\Helper\PpscParticipant;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'orders')]
#[ORM\UniqueConstraint(name: 'order_id', columns: ['order_id'])]
#[ORM\Entity(repositoryClass: 'App\Repository\OrderRepository')]
class Order
{
    public const FIXED_ANGLE = 'fixed_angle';
    public const SWINGING_BUCKET = 'swinging_bucket';
    public const ORDER_ACTIVE = 'active';
    public const ORDER_CANCEL = 'cancel';
    public const ORDER_RESTORE = 'restore';
    public const ORDER_UNLOCK = 'unlock';
    public const ORDER_EDIT = 'edit';
    public const ORDER_REVERT = 'revert';
    public const INITIAL_VERSION = '1';
    public const ORDER_STEP_FINALIZED = 'finalized';
    public const ORDER_STEP_COLLECTED = 'collected';
    public const ORDER_TYPE_KIT = 'kit';
    public const ORDER_TYPE_DIVERSION = 'diversion';
    public const ORDER_TYPE_SALIVA = 'saliva';
    public const PEDIATRIC_ORDER_STRING = 'ped';
    public const PEDIATRIC_BLOOD_SAMPLES = ['1ED04', '2ED02', '2ED04', '1ED10', '1PXR2', '1ED02'];
    public const PEDIATRIC_URINE_SAMPLES = ['1UR10'];
    public const PEDIATRIC_SALIVA_SAMPLES = ['1SAL2', '2SAL0'];
    public const TUBE_SELECTION_TYPE = 'tubeSelect';
    public const PEDIATRIC_SALIVA_SAMPLE_DEFAULT = '1SAL2';
    public const PEDIATRIC_SALIVA_SAMPLE_HIDE = '2SAL0';

    public static $samplesRequiringProcessing = ['1SST8', '1PST8', '1SS08', '1PS08', 'PS04A', 'PS04B'];

    public static $samplesRequiringCentrifugeType = ['1SS08', '1PS08', 'PS04A', 'PS04B'];

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

    public static $pst = ['1PST8', '1PS08', 'PS04A', 'PS04B'];

    public static $sampleMessageLabels = [
        '1SST8' => 'sst',
        '1SS08' => 'sst',
        '1PST8' => 'pst',
        '1PS08' => 'pst',
        '1SAL' => 'sal',
        '1SAL2' => 'sal',
        'PS04A' => 'pst',
        'PS04B' => 'pst'
    ];

    public static $nonBloodSamples = ['1UR10', '1UR90', '1SAL', '1SAL2', '2SAL0'];

    public static $urineSamples = ['1UR10', '1UR90'];

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
        ],
        '1PS4A' => [
            'code' => 'PS04A',
            'centrifuge_type' => 'swinging_bucket'
        ],
        '2PS4A' => [
            'code' => 'PS04A',
            'centrifuge_type' => 'fixed_angle'
        ],
        '1PS4B' => [
            'code' => 'PS04B',
            'centrifuge_type' => 'swinging_bucket'
        ],
        '2PS4B' => [
            'code' => 'PS04B',
            'centrifuge_type' => 'fixed_angle'
        ],
    ];

    public static array $hpoToRdrSampleConversions = [
        '1SS08' => ['fixed_angle' => '2SST8', 'swinging_bucket' => '1SST8'],
        '1PS08' => ['fixed_angle' => '2PST8', 'swinging_bucket' => '1PST8'],
        'PS04A' => ['fixed_angle' => '2PS4A', 'swinging_bucket' => '1PS4A'],
        'PS04B' => ['fixed_angle' => '2PS4B', 'swinging_bucket' => '1PS4B']
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

    private $params;
    private $samples;
    private $samplesInformation;
    private $salivaSamples;
    private $salivaSamplesInformation;
    private $salivaInstructions;
    private $currentVersion;
    private $origin;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\OneToOne(targetEntity: 'App\Entity\User', cascade: ['persist', 'remove'])]
    private $user;

    #[ORM\Column(type: 'string', length: 50)]
    private $site;

    #[ORM\Column(type: 'string', length: 50)]
    private $participantId;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $rdrId;

    #[ORM\Column(type: 'string', length: 50)]
    private $biobankId;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private $createdTs;

    #[ORM\Column(type: 'string', length: 100)]
    private $orderId;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $mayoId;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $requestedSamples;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $printedTs;

    #[ORM\OneToOne(targetEntity: 'App\Entity\User', cascade: ['persist', 'remove'])]
    private $collectedUser;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $collectedSite;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $collectedTs;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $collectedSamples;

    #[ORM\Column(type: 'text', nullable: true)]
    private $collectedNotes;

    #[ORM\OneToOne(targetEntity: 'App\Entity\User', cascade: ['persist', 'remove'])]
    private $processedUser;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $processedSite;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $processedTs;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $processedSamples;

    #[ORM\Column(type: 'string', nullable: true)]
    private $processedSamplesTs;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $processedCentrifugeType;

    #[ORM\Column(type: 'text', nullable: true)]
    private $processedNotes;

    #[ORM\OneToOne(targetEntity: 'App\Entity\User', cascade: ['persist', 'remove'])]
    private $finalizedUser;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $finalizedTs;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $finalizedSamples;

    #[ORM\Column(type: 'text', nullable: true)]
    private $finalizedNotes;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $fedexTracking;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private $type;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private $version;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private $biobankFinalized = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private $biobankChanges;

    #[ORM\OneToOne(targetEntity: 'App\Entity\OrderHistory', cascade: ['persist', 'remove'])]
    private $history;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $finalizedSite;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $createdTimezoneId;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $collectedTimezoneId;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $processedTimezoneId;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $finalizedTimezoneId;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $submissionTs;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $ageInMonths;

    private $quanumCollectedUser;

    private $quanumProcessedUser;

    private $quanumFinalizedUser;

    private $collectedSiteName;

    private $processedSiteName;

    private $finalizedSiteName;

    private $collectedSiteAddress;

    private $quanumFinalizedSamples;

    private $quanumOrderStatus;


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

    public function getCreatedTs(): ?DateTime
    {
        return $this->createdTs;
    }

    public function setCreatedTs(DateTime $createdTs): self
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

    public function getPrintedTs(): ?DateTimeInterface
    {
        return $this->printedTs;
    }

    public function setPrintedTs(?DateTimeInterface $printedTs): self
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

    public function getCollectedTs(): ?DateTime
    {
        return $this->collectedTs;
    }

    public function setCollectedTs(?DateTime $collectedTs): self
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

    public function getProcessedTs(): ?DateTimeInterface
    {
        return $this->processedTs;
    }

    public function setProcessedTs(?DateTimeInterface $processedTs): self
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

    public function getFinalizedTs(): ?DateTimeInterface
    {
        return $this->finalizedTs;
    }

    public function setFinalizedTs(?DateTimeInterface $finalizedTs): self
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

    public function getCreatedTimezoneId(): ?int
    {
        return $this->createdTimezoneId;
    }

    public function setCreatedTimezoneId(?int $createdTimezoneId): self
    {
        $this->createdTimezoneId = $createdTimezoneId;

        return $this;
    }

    public function getCollectedTimezoneId(): ?int
    {
        return $this->collectedTimezoneId;
    }

    public function setCollectedTimezoneId(?int $collectedTimezoneId): self
    {
        $this->collectedTimezoneId = $collectedTimezoneId;

        return $this;
    }

    public function getProcessedTimezoneId(): ?int
    {
        return $this->processedTimezoneId;
    }

    public function setProcessedTimezoneId(?int $processedTimezoneId): self
    {
        $this->processedTimezoneId = $processedTimezoneId;

        return $this;
    }

    public function getFinalizedTimezoneId(): ?int
    {
        return $this->finalizedTimezoneId;
    }

    public function setFinalizedTimezoneId(?int $finalizedTimezoneId): self
    {
        $this->finalizedTimezoneId = $finalizedTimezoneId;

        return $this;
    }

    public function getCreatedTimezone(): ?string
    {
        return User::$timezones[$this->createdTimezoneId] ?? null;
    }

    public function getCollectedTimezone(): ?string
    {
        return User::$timezones[$this->collectedTimezoneId] ?? null;
    }

    public function getProcessedTimezone(): ?string
    {
        return User::$timezones[$this->processedTimezoneId] ?? null;
    }

    public function getFinalizedTimezone(): ?string
    {
        return User::$timezones[$this->finalizedTimezoneId] ?? null;
    }

    public function getSubmissionTs(): ?DateTimeInterface
    {
        return $this->submissionTs;
    }

    public function setSubmissionTs(?DateTimeInterface $submissionTs): self
    {
        $this->submissionTs = $submissionTs;

        return $this;
    }

    public function getHistory(): ?OrderHistory
    {
        return $this->history;
    }

    public function setHistory(?OrderHistory $history): self
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

    // Used to determine quanum orders
    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(?string $origin): self
    {
        $this->origin = $origin;

        return $this;
    }

    public function getQuanumCollectedUser(): ?string
    {
        return $this->quanumCollectedUser;
    }

    public function setQuanumCollectedUser(?string $quanumCollectedUser): self
    {
        $this->quanumCollectedUser = $quanumCollectedUser;

        return $this;
    }

    public function getQuanumProcessedUser(): ?string
    {
        return $this->quanumProcessedUser;
    }

    public function setQuanumProcessedUser(?string $quanumProcessedUser): self
    {
        $this->quanumProcessedUser = $quanumProcessedUser;

        return $this;
    }

    public function getQuanumFinalizedUser(): ?string
    {
        return $this->quanumFinalizedUser;
    }

    public function setQuanumFinalizedUser(?string $quanumFinalizedUser): self
    {
        $this->quanumFinalizedUser = $quanumFinalizedUser;

        return $this;
    }

    public function setCollectedSiteName(?string $collectedSiteName): self
    {
        $this->collectedSiteName = $collectedSiteName;

        return $this;
    }

    public function getCollectedSiteName(): ?string
    {
        return $this->collectedSiteName;
    }

    public function setProcessedSiteName(?string $processedSiteName): self
    {
        $this->processedSiteName = $processedSiteName;

        return $this;
    }

    public function getProcessedSiteName(): ?string
    {
        return $this->processedSiteName;
    }

    public function setFinalizedSiteName(?string $finalizedSiteName): self
    {
        $this->finalizedSiteName = $finalizedSiteName;

        return $this;
    }

    public function getFinalizedSiteName(): ?string
    {
        return $this->finalizedSiteName;
    }


    public function setCollectedSiteAddress(?string $collectedSiteAddress): self
    {
        $this->collectedSiteAddress = $collectedSiteAddress;

        return $this;
    }

    public function getQuanumFinalizedSamples(): ?string
    {
        return $this->quanumFinalizedSamples;
    }

    public function setQuanumFinalizedSamples(?string $quanumFinalizedSamples): self
    {
        $this->quanumFinalizedSamples = $quanumFinalizedSamples;

        return $this;
    }

    public function getCollectedSiteAddress(): ?string
    {
        return $this->collectedSiteAddress;
    }

    public function setQuanumOrderStatus(?string $quanumOrderStatus): self
    {
        $this->quanumOrderStatus = $quanumOrderStatus;

        return $this;
    }

    public function getQuanumOrderStatus(): ?string
    {
        return $this->quanumOrderStatus;
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

    public function getAgeInMonths(): int
    {
        return $this->ageInMonths;
    }

    public function setAgeInMonths(?int $ageInMonths): self
    {
        $this->ageInMonths = $ageInMonths;

        return $this;
    }

    public function loadSamplesSchema($params = [], PpscParticipant $participant = null, Measurement $physicalMeasurement = null)
    {
        $this->currentVersion = $this->getVersion();
        if ($participant) {
            $pediatricFlag = $participant->isPediatric;
        } else {
            $pediatricFlag = false;
        }
        if (empty($this->currentVersion)) {
            if (!empty($this->getId())) {
                // Initial orders doesn't have a version so set version for those orders
                $this->currentVersion = self::INITIAL_VERSION;
                if ($pediatricFlag) {
                    $summary = $physicalMeasurement?->getSummary();
                    $weight = $summary ? $summary['weight']['kg'] : null;
                    $this->currentVersion = $params['pediatric_order_samples_version'] . '-' . self::PEDIATRIC_ORDER_STRING . "-{$participant->getPediatricWeightBreakpoint($weight)}";
                }
            } elseif (!empty($params['order_samples_version'])) {
                $this->currentVersion = $params['order_samples_version'];
                if ($pediatricFlag) {
                    $summary = $physicalMeasurement?->getSummary();
                    $weight = $summary ? $summary['weight']['kg'] : null;
                    $this->currentVersion = $params['pediatric_order_samples_version'] . '-' . self::PEDIATRIC_ORDER_STRING . "-{$participant->getPediatricWeightBreakpoint($weight)}";
                }
            }
        }

        $this->params = $params;
        $file = __DIR__ . "/../Order/versions/{$this->currentVersion}.json";
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
        if ($this->getType() === self::ORDER_TYPE_KIT) {
            $identifiers[] = [
                'system' => 'https://orders.mayomedicallaboratories.com/kit-id',
                'value' => $this->getOrderId()
            ];
        }
        if (!empty($this->getFedexTracking())) {
            $identifiers[] = [
                'system' => 'https://orders.mayomedicallaboratories.com/tracking-number',
                'value' => $this->getFedexTracking()
            ];
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
        // Set processed user and site info to collected for saliva orders
        if ($this->getType() !== 'saliva') {
            $processedUser = $this->getOrderUser($this->getProcessedUser());
            $processedSite = $this->getOrderSite($this->getProcessedSite());
        } else {
            $processedUser = $collectedUser;
            $processedSite = $collectedSite;
        }
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
        foreach (['collected', 'processed', 'finalized'] as $step) {
            if ($this->{'get' . ucfirst($step) . 'Notes'}()) {
                $notes[$step] = $this->{'get' . ucfirst($step) . 'Notes'}();
            }
        }
        if (!empty($notes)) {
            $obj->notes = $notes;
        }
        return $obj;
    }

    public function getEditRdrObject()
    {
        $obj = $this->getRdrObject();
        $obj->amendedReason = $this->getHistory()->getReason();
        $user = $this->getOrderUser($this->getHistory()->getUser());
        $site = $this->getOrderSite($this->getHistory()->getSite());
        $obj->amendedInfo = $this->getOrderUserSiteData($user, $site);
        return $obj;
    }

    public function getOrderUser($user)
    {
        if ($this->getBiobankFinalized() && empty($user)) {
            return 'BiobankUser';
        }
        $user = $user ?: $this->getUser();
        return $user->getEmail() ?? '';
    }

    public function getOrderSite($site)
    {
        return $site ?: $this->getSite();
    }

    public function getOrderUserSiteData($user, $site)
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

    public function getStatus()
    {
        $history = $this->getHistory();
        if (!empty($history)) {
            return !empty($history->getType()) ? $history->getType() : self::ORDER_ACTIVE;
        }
    }

    public function isExpired()
    {
        return empty($this->getFinalizedTs()) && empty($this->getVersion()) && $this->getType() !== self::TUBE_SELECTION_TYPE;
    }

    // Finalized form is only disabled when rdr_id is set
    public function isDisabled()
    {
        return ($this->getRdrId() || $this->isExpired() || $this->isCancelled()) && $this->getStatus() !== 'unlock';
    }

    // Except finalize form all forms are disabled when finalized_ts is set
    public function isFormDisabled()
    {
        return ($this->getFinalizedTs() || $this->isExpired() || $this->isCancelled()) && $this->getStatus() !== 'unlock';
    }

    public function isCancelled()
    {
        return $this->getStatus() === self::ORDER_CANCEL;
    }

    public function isUnlocked()
    {
        return $this->getStatus() === self::ORDER_UNLOCK;
    }

    public function isFailedToReachRdr()
    {
        return !empty($this->getFinalizedTs()) && !empty($this->getMayoId()) && empty($this->getRdrId());
    }

    public function canCancel()
    {
        return !$this->isCancelled() && !$this->isUnlocked() && !$this->isFailedToReachRdr();
    }

    public function canRestore()
    {
        return !$this->isExpired() && $this->isCancelled() && !$this->isUnlocked() && !$this->isFailedToReachRdr();
    }

    public function canUnlock()
    {
        return !$this->isExpired() && !empty($this->getRdrId()) && !$this->isUnlocked() && !$this->isCancelled() && !empty($this->getVersion());
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
            if (in_array($sample, self::$nonBloodSamples)) {
                return $sample;
            }
        }
        return null;
    }

    // Returns sample's code and display text
    public function getCustomRequestedSamples()
    {
        if ($this->getType() == 'saliva') {
            return $this->salivaSamples;
        }
        if ($this->getRequestedSamples() &&
            ($requestedArray = json_decode($this->getRequestedSamples())) &&
            is_array($requestedArray)
        ) {
            return array_intersect($this->samples, $requestedArray);
        }
        return $this->samples;
    }

    public function getEnabledSamples($set)
    {
        if ($this->getCollectedSamples() &&
            ($collectedArray = json_decode($this->getCollectedSamples())) &&
            is_array($collectedArray)
        ) {
            $collected = $collectedArray;
        } else {
            $collected = [];
        }

        if ($this->getProcessedSamples() &&
            ($processedArray = json_decode($this->getProcessedSamples())) &&
            is_array($processedArray)
        ) {
            $processed = $processedArray;
        } else {
            $processed = [];
        }

        switch ($set) {
            case 'processed':
                return array_intersect($collected, self::$samplesRequiringProcessing, $this->getCustomRequestedSamples());
            case 'finalized':
                $enabled = array_intersect($collected, $this->getCustomRequestedSamples());
                foreach ($enabled as $key => $sample) {
                    if (in_array($sample, self::$samplesRequiringProcessing) &&
                        !in_array($sample, $processed)
                    ) {
                        unset($enabled[$key]);
                    }
                }
                return array_values($enabled);
            default:
                return array_values($this->getCustomRequestedSamples());
        }
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
        if ($this->getType() === 'kit' || $this->getType() === self::TUBE_SELECTION_TYPE) {
            unset($columns['print_labels']);
            unset($columns['print_requisition']);
        }
        if ($this->getType() === 'saliva') {
            unset($columns['process']);
        }
        if ($this->isPediatricOrder()) {
            unset($columns['process']);
        }
        $step = 'finalize';
        foreach ($columns as $name => $column) {
            if (!$this->{'get' . $column . 'Ts'}()) {
                $step = $name;
                break;
            }
        }
        // For canceled orders set print labels step to collect
        if ($this->isCancelled() && $step === 'print_labels') {
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
        if ($this->getType() === 'kit' || $this->getType() === self::TUBE_SELECTION_TYPE) {
            unset($columns['print_labels']);
            unset($columns['print_requisition']);
        }
        if ($this->getType() === 'saliva') {
            unset($columns['process']);
        }
        if ($this->isPediatricOrder()) {
            unset($columns['process']);
        }
        $steps = [];
        foreach ($columns as $name => $column) {
            $steps[] = $name;
            if (!$this->{'get' . $column . 'Ts'}()) {
                break;
            }
        }
        // For canceled orders include collect in available steps if not exists
        if ($this->isCancelled() && !in_array('collect', $steps)) {
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

            foreach ($pst as $sample) {
                if (!empty($processedSamplesTs[$sample]) && $processedSamplesTs[$sample] > $collectedTs->getTimestamp()) {
                    $warnings['pst'] = 'Processing Time is Greater than 4 hours after Collection';
                    break;
                }
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

    public function getBiobankChangesDetails($timeZone)
    {
        $samplesInfo = $this->getType() === 'saliva' ? $this->salivaSamplesInformation : $this->samplesInformation;
        if ($this->getType() === 'saliva') {
            // Set color to empty string for saliva samples
            foreach (array_keys($samplesInfo) as $key) {
                if (empty($samplesInfo[$key]['color'])) {
                    $samplesInfo[$key]['color'] = '';
                }
            }
        }

        $biobankChanges = !empty($this->getBiobankChanges()) ? json_decode($this->getBiobankChanges(), true) : [];
        if (!empty($biobankChanges['collected']['time'])) {
            $collectedTs = new DateTime();
            $collectedTs->setTimestamp($biobankChanges['collected']['time']);
            $collectedTs->setTimezone(new \DateTimeZone($timeZone));
            $biobankChanges['collected']['time'] = $collectedTs;
        }

        if (!empty($biobankChanges['collected']['samples'])) {
            $sampleDetails = [];
            foreach ($biobankChanges['collected']['samples'] as $sample) {
                $sampleDetails[$sample]['code'] = array_search($sample, $this->getCustomRequestedSamples());
                $sampleDetails[$sample]['color'] = $samplesInfo[$sample]['color'];
            }
            $biobankChanges['collected']['sample_details'] = $sampleDetails;
        }

        if (!empty($biobankChanges['processed']['samples_ts'])) {
            $sampleDetails = [];
            foreach ($biobankChanges['processed']['samples_ts'] as $sample => $time) {
                $sampleDetails[$sample]['code'] = array_search($sample, $this->getCustomRequestedSamples());
                $sampleDetails[$sample]['color'] = $samplesInfo[$sample]['color'];
                $processedTs = new DateTime();
                $processedTs->setTimestamp($time);
                $processedTs->setTimezone(new \DateTimeZone($timeZone));
                $sampleDetails[$sample]['time'] = $processedTs;
            }
            $biobankChanges['processed']['sample_details'] = $sampleDetails;
        }

        if (!empty($biobankChanges['processed']['centrifuge_type'])) {
            $biobankChanges['processed']['centrifuge_type'] = self::$centrifugeType[$biobankChanges['processed']['centrifuge_type']];
        }

        if (!empty($biobankChanges['finalized']['time'])) {
            $collectedTs = new DateTime();
            $collectedTs->setTimestamp($biobankChanges['finalized']['time']);
            $collectedTs->setTimezone(new \DateTimeZone($timeZone));
            $biobankChanges['finalized']['time'] = $collectedTs;
        }

        if (!empty($biobankChanges['finalized']['samples'])) {
            $sampleDetails = [];
            foreach ($biobankChanges['finalized']['samples'] as $sample) {
                $sampleDetails[$sample]['code'] = array_search($sample, $this->getCustomRequestedSamples());
                $sampleDetails[$sample]['color'] = $samplesInfo[$sample]['color'];
            }
            $biobankChanges['finalized']['sample_details'] = $sampleDetails;
        }

        return $biobankChanges;
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

    public function checkBiobankChanges($collectedTs, $finalizedTs, $finalizedSamples, $finalizedNotes, $centrifugeType, $timezoneId)
    {
        $biobankChanges = [];
        $collectedSamples = !empty($this->getCollectedSamples()) ? json_decode($this->getCollectedSamples(), true) : [];
        $processedSamples = !empty($this->getProcessedSamples()) ? json_decode($this->getProcessedSamples(), true) : [];
        $processedSamplesTs = !empty($this->getProcessedSamplesTs()) ? json_decode($this->getProcessedSamplesTs(), true) : [];
        $collectedSamplesDiff = array_values(array_diff($finalizedSamples, $collectedSamples));
        $finalizedProcessSamples = $this->getFinalizedProcessSamples($finalizedSamples);
        $processedSamplesDiff = array_values(array_diff($finalizedProcessSamples, $processedSamples));
        $createdTs = $this->getCreatedTs();
        if (empty($collectedTs)) {
            // Collected ts should already been set
            $this->setCollectedUser(null);
            $biobankChanges['collected'] = [
                'time' => $createdTs->getTimestamp(),
                'user' => null
            ];
        }
        if (empty($collectedSamples) || !empty($collectedSamplesDiff)) {
            $this->setCollectedSite($this->getSite());
            $this->setCollectedSamples(json_encode(array_merge($collectedSamples, $collectedSamplesDiff)));
            $biobankChanges['collected']['site'] = $this->getSite();
            $biobankChanges['collected']['samples'] = $collectedSamplesDiff;
        }
        // Do not set processed time for saliva orders
        if ($this->type !== 'saliva' && empty($processedSamplesTs)) {
            $this->setProcessedTs($createdTs);
            $this->setProcessedTimezoneId($timezoneId);
            $this->setProcessedUser(null);
            $biobankChanges['processed'] = [
                'time' => $createdTs->getTimestamp(),
                'user' => null
            ];
            $createdProcessSamplesTs = [];
            foreach ($finalizedProcessSamples as $sample) {
                $createdProcessSamplesTs[$sample] = $createdTs->getTimestamp();
            }
            $this->setProcessedSite($this->getSite());
            $this->setProcessedSamples(json_encode($finalizedProcessSamples));
            $this->setProcessedSamplesTs(json_encode($createdProcessSamplesTs));
            $biobankChanges['processed']['site'] = $this->getSite();
            $biobankChanges['processed']['samples'] = $finalizedProcessSamples;
            $biobankChanges['processed']['samples_ts'] = $createdProcessSamplesTs;
        }
        if (!empty($processedSamplesTs) && !empty($processedSamplesDiff)) {
            $totalProcessedSamples = array_merge($processedSamples, $processedSamplesDiff);
            $newProcessedSampleTs = [];
            foreach ($processedSamplesDiff as $sample) {
                $newProcessedSampleTs[$sample] = $createdTs->getTimestamp();
            }
            $this->setProcessedSite($this->getSite());
            $this->setProcessedSamples(json_encode($totalProcessedSamples));
            $this->setProcessedSamplesTs(json_encode(array_merge($newProcessedSampleTs, $processedSamplesTs)));
            $biobankChanges['processed']['site'] = $this->getSite();
            $biobankChanges['processed']['samples'] = $processedSamplesDiff;
            $biobankChanges['processed']['samples_ts'] = $newProcessedSampleTs;
        }
        if (!empty($centrifugeType)) {
            $this->setProcessedCentrifugeType($centrifugeType);
            $biobankChanges['processed']['centrifuge_type'] = $centrifugeType;
        }
        $this->setFinalizedTs($finalizedTs);
        $this->setSubmissionTs($finalizedTs);
        $this->setFinalizedTimezoneId($timezoneId);
        $this->setFinalizedSite($this->getSite());
        $this->setFinalizedUser(null);
        $this->setFinalizedNotes($finalizedNotes);
        $this->setFinalizedSamples(json_encode($finalizedSamples));
        $biobankChanges['finalized'] = [
            'time' => $finalizedTs->getTimestamp(),
            'site' => $this->getSite(),
            'user' => null,
            'notes' => $finalizedNotes,
            'samples' => $finalizedSamples
        ];
        $this->setBiobankFinalized(true);
        $this->setBiobankChanges(json_encode($biobankChanges));
    }

    public function isUrineOrder(): bool
    {
        $requestedSamples = json_decode($this->requestedSamples, true);
        return is_array($requestedSamples) && empty(array_diff($requestedSamples, self::$urineSamples));
    }

    public function isPediatricSalivaOrder(): bool
    {
        $requestedSamples = json_decode($this->requestedSamples, true);
        return is_array($requestedSamples) && empty(array_diff($requestedSamples, self::PEDIATRIC_SALIVA_SAMPLES));
    }

    public function getOrderTypeDisplayText(): string
    {
        if (!empty($this->requestedSamples)) {
            if ($this->isPediatricOrder()) {
                if ($this->isUrineOrder()) {
                    return 'Pediatric Urine';
                }
                if ($this->isPediatricSalivaOrder()) {
                    return 'Pediatric Saliva';
                }
                return 'Pediatric Blood';
            }
            if ($this->isUrineOrder()) {
                return 'Urine';
            }
            if ($this->type !== 'kit') {
                return 'Custom HPO';
            }
        }
        if ($this->type === 'kit') {
            return 'Full Kit';
        }
        if ($this->type === 'saliva') {
            return 'Saliva';
        }
        return 'Full HPO';
    }

    public function hideTrackingFieldByDefault(): bool
    {
        return $this->getFedexTracking() === null && ($this->getType() === self::ORDER_TYPE_KIT || $this->getType()
                === self::ORDER_TYPE_DIVERSION);
    }

    public function getPediatricBloodSamples(): array
    {
        $samples = [];
        foreach ($this->samples as $sample) {
            if (in_array($sample, self::PEDIATRIC_BLOOD_SAMPLES)) {
                $samples[] = $sample;
            }
        }
        return $samples;
    }

    public function getPediatricUrineSamples(): array
    {
        $samples = [];
        foreach ($this->samples as $sample) {
            if (in_array($sample, self::PEDIATRIC_URINE_SAMPLES)) {
                $samples[] = $sample;
            }
        }
        return $samples;
    }

    public function getPediatricSalivaSamples(): array
    {
        return [self::PEDIATRIC_SALIVA_SAMPLE_DEFAULT];
    }

    public function isPediatricOrder(): bool
    {
        if ($this->version) {
            return str_contains($this->version, self::PEDIATRIC_ORDER_STRING);
        } elseif ($this->getCurrentVersion()) {
            return str_contains($this->getCurrentVersion(), self::PEDIATRIC_ORDER_STRING);
        }
        return false;
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
                    $time = new DateTime();
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
            if (array_key_exists($test, self::$hpoToRdrSampleConversions)) {
                $rdrTest = self::$hpoToRdrSampleConversions[$test][$this->getProcessedCentrifugeType()] ?? self::$hpoToRdrSampleConversions[$test][self::SWINGING_BUCKET];
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
        $requestedSamples = $this->getRequestedSamples();
        $decodedSamples = $requestedSamples ? json_decode($requestedSamples) : null;
        $hasValidRequestedSamples = is_array($decodedSamples);
        if ($this->getType() === self::ORDER_TYPE_SALIVA) {
            if ($this->isPediatricOrder() && $hasValidRequestedSamples) {
                return array_intersect($this->salivaSamples, $decodedSamples);
            }
            return $this->salivaSamples;
        }
        if ($hasValidRequestedSamples) {
            return array_intersect($this->samples, $decodedSamples);
        }
        return $this->samples;
    }
}
