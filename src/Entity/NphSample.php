<?php

namespace App\Entity;

use App\Form\Nph\NphOrderForm;
use App\Repository\NphSampleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'nph_samples')]
#[ORM\UniqueConstraint(name: 'sample_id', columns: ['sample_id'])]
#[ORM\Entity(repositoryClass: NphSampleRepository::class)]
class NphSample
{
    public const CANCEL = 'cancel';
    public const RESTORE = 'restore';
    public const UNLOCK = 'unlock';
    public const EDITED = 'edited';
    public const REVERT = 'revert';
    public const SAMPLE_STOOL = 'STOOL';
    public const SAMPLE_STOOL_2 = 'STOOL2';
    public const STOOL_TIMEPOINTS = ['preLMT', 'preDSMT'];
    public const BIOBANK_MODIFY_REASON = 'biobank';
    public const NPH_ADMIN_MODIFY_REASON = 'admin';
    public const SAMPLE_URINE_24 = 'URINE24';
    public const DISPLAY_CANCEL = 'Canceled';
    public const DISPLAY_UNLOCK = 'Unlocked';
    public const DISPLAY_CREATED = 'Created';
    public const DISPLAY_COLLECTED = 'Collected';
    public const DISPLAY_BIOBANK_FINALIZED = 'Biobank Finalized';
    public const DISPLAY_FINALIZED = 'Finalized';

    private const RDR_MICROLITER_UNITS = [
        'μL' => 'uL'
    ];

    public static $cancelReasons = [
        'Created in error' => 'CANCEL_ERROR',
        'Created for wrong participant' => 'CANCEL_WRONG_PARTICIPANT',
        'Labeling error identified after finalization' => 'CANCEL_LABEL_ERROR',
        'Other' => 'OTHER'
    ];

    public static $restoreReasons = [
        'Cancelled for wrong participant' => 'RESTORE_WRONG_PARTICIPANT',
        'Can be edited instead of cancelled' => 'RESTORE_AMEND',
        'Other' => 'OTHER'
    ];

    public static $unlockReasons = [
        'Change collection information' => 'CHANGE_COLLECTION_INFORMATION',
        'Change, add, or remove aliquot' => 'CHANGE_ADD_REMOVE_ALIQUOT',
        'Other' => 'OTHER'
    ];

    public static $modifySuccessText = [
        'cancel' => 'cancelled',
        'restore' => 'restored',
        'unlock' => 'unlocked'
    ];

    public static array $stoolSamples = [
        'STOOL',
        'ST1',
        'ST2',
        'ST3',
        'ST4',
        'STOOL2',
        'ST5',
        'ST6',
        'ST7',
        'ST8',
        'stoolKit',
        'stoolKit2'
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 100)]
    private $sampleId;

    #[ORM\ManyToOne(targetEntity: NphOrder::class, inversedBy: 'nphSamples')]
    #[ORM\JoinColumn(nullable: false)]
    private $nphOrder;

    #[ORM\Column(type: 'string', length: 50)]
    private $sampleCode;

    #[ORM\Column(type: 'text', nullable: true)]
    private $sampleMetadata;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $collectedSite;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private $collectedUser;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $collectedTs;

    #[ORM\Column(type: 'text', nullable: true)]
    private $collectedNotes;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $finalizedSite;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private $finalizedUser;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $finalizedTs;

    #[ORM\Column(type: 'text', nullable: true)]
    private $finalizedNotes;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $rdrId;

    #[ORM\OneToMany(targetEntity: NphAliquot::class, mappedBy: 'nphSample')]
    private $nphAliquots;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private $modifiedUser;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $modifiedSite;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $modifiedTs;

    #[ORM\Column(type: 'text', nullable: true)]
    private $modifyReason;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $modifyType;

    #[ORM\Column(type: 'integer', nullable: false)]
    private $sampleGroup;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $collectedTimezoneId;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $finalizedTimezoneId;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $modifiedTimezoneId;
    #[ORM\Column(type: 'boolean', nullable: true)]
    private $biobankFinalized;

    public function __construct()
    {
        $this->nphAliquots = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSampleId(): ?string
    {
        return $this->sampleId;
    }

    public function setSampleId(string $sampleId): self
    {
        $this->sampleId = $sampleId;

        return $this;
    }

    public function getNphOrder(): ?NphOrder
    {
        return $this->nphOrder;
    }

    public function setNphOrder(?NphOrder $nphOrder): self
    {
        $this->nphOrder = $nphOrder;
        // This loads collection data in phpunit
        $nphOrder->addNphSample($this);
        return $this;
    }

    public function getSampleCode(): ?string
    {
        return $this->sampleCode;
    }

    public function setSampleCode(string $sampleCode): self
    {
        $this->sampleCode = $sampleCode;

        return $this;
    }

    public function getSampleMetadata(): ?string
    {
        return $this->sampleMetadata;
    }

    public function setSampleMetadata(?string $sampleMetadata): self
    {
        $this->sampleMetadata = $sampleMetadata;

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

    public function getCollectedUser(): ?User
    {
        return $this->collectedUser;
    }

    public function setCollectedUser(?User $collectedUser): self
    {
        $this->collectedUser = $collectedUser;

        return $this;
    }

    public function getCollectedTs(): ?\DateTime
    {
        return $this->collectedTs;
    }

    public function setCollectedTs(?\DateTime $collectedTs): self
    {
        $this->collectedTs = $collectedTs;

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

    public function getFinalizedSite(): ?string
    {
        return $this->finalizedSite;
    }

    public function setFinalizedSite(?string $finalizedSite): self
    {
        $this->finalizedSite = $finalizedSite;

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

    public function getFinalizedTs(): ?\DateTime
    {
        return $this->finalizedTs;
    }

    public function setFinalizedTs(?\DateTime $finalizedTs): self
    {
        $this->finalizedTs = $finalizedTs;

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

    public function getRdrId(): ?string
    {
        return $this->rdrId;
    }

    public function setRdrId(?string $rdrId): self
    {
        $this->rdrId = $rdrId;

        return $this;
    }

    public function setBiobankFinalized(bool $biobankFinalized): self
    {
        $this->biobankFinalized = $biobankFinalized;

        return $this;
    }

    public function getBiobankFinalized(): ?bool
    {
        return $this->biobankFinalized;
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

    public function getFinalizedTimezoneId(): ?int
    {
        return $this->finalizedTimezoneId;
    }

    public function setFinalizedTimezoneId(?int $finalizedTimezoneId): self
    {
        $this->finalizedTimezoneId = $finalizedTimezoneId;

        return $this;
    }

    public function getModifiedTimezoneId(): ?int
    {
        return $this->modifiedTimezoneId;
    }

    public function setModifiedTimezoneId(?int $modifiedTimezoneId): self
    {
        $this->modifiedTimezoneId = $modifiedTimezoneId;

        return $this;
    }

    public function getStatus(): string
    {
        if ($this->modifyType === NphSample::CANCEL) {
            return self::DISPLAY_CANCEL;
        }

        if ($this->modifyType === NphSample::UNLOCK) {
            return self::DISPLAY_UNLOCK;
        }

        if ($this->collectedTs === null && $this->finalizedTs === null) {
            return self::DISPLAY_CREATED;
        }

        if ($this->finalizedTs === null) {
            return self::DISPLAY_COLLECTED;
        }

        if ($this->biobankFinalized) {
            return self::DISPLAY_BIOBANK_FINALIZED;
        }

        return self::DISPLAY_FINALIZED;
    }

    public function isFinalized(): bool
    {
        return $this->getStatus() === self::DISPLAY_FINALIZED || $this->getStatus() === self::DISPLAY_BIOBANK_FINALIZED;
    }

    /**
     * @return Collection|NphAliquot[]
     */
    public function getNphAliquots(): Collection
    {
        return $this->nphAliquots;
    }

    public function getNphAliquotsStatus(): array
    {
        $aliquotsStatus = [];
        foreach ($this->nphAliquots as $aliquot) {
            $aliquotsStatus[$aliquot->getAliquotId()] = $aliquot->getStatus();
        }
        return $aliquotsStatus;
    }

    public function getNphAliquotIds(): array
    {
        $aliquotIds = [];
        foreach ($this->nphAliquots as $aliquot) {
            $aliquotIds[] = $aliquot->getAliquotId();
        }
        return $aliquotIds;
    }

    public function addNphAliquot(NphAliquot $nphAliquot): self
    {
        if (!$this->nphAliquots->contains($nphAliquot)) {
            $this->nphAliquots[] = $nphAliquot;
            $nphAliquot->setNphSample($this);
        }

        return $this;
    }

    public function removeNphAliquot(NphAliquot $nphAliquot): self
    {
        if ($this->nphAliquots->removeElement($nphAliquot)) {
            // set the owning side to null (unless already changed)
            if ($nphAliquot->getNphSample() === $this) {
                $nphAliquot->setNphSample(null);
            }
        }

        return $this;
    }

    public function getModifiedUser(): ?User
    {
        return $this->modifiedUser;
    }

    public function setModifiedUser(?User $modifiedUser): self
    {
        $this->modifiedUser = $modifiedUser;

        return $this;
    }

    public function getModifiedSite(): ?string
    {
        return $this->modifiedSite;
    }

    public function setModifiedSite(?string $modifiedSite): self
    {
        $this->modifiedSite = $modifiedSite;

        return $this;
    }

    public function getModifiedTs(): ?\DateTime
    {
        return $this->modifiedTs;
    }

    public function setModifiedTs(?\DateTime $modifiedTs): self
    {
        $this->modifiedTs = $modifiedTs;

        return $this;
    }

    public function getModifyReason(): ?string
    {
        return $this->modifyReason;
    }

    public function setModifyReason(?string $modifyReason): self
    {
        $this->modifyReason = $modifyReason;

        return $this;
    }

    public function getModifyType(): ?string
    {
        return $this->modifyType;
    }

    public function setModifyType(?string $modifyType): self
    {
        $this->modifyType = $modifyType;

        return $this;
    }

    public function isDisabled(): bool
    {
        return ($this->rdrId || $this->modifyType === self::CANCEL) && $this->getModifyType() !== self::UNLOCK;
    }

    public function isUnlocked(): bool
    {
        return $this->getModifyType() === self::UNLOCK;
    }

    public function getModifyReasonDisplayText(): string
    {
        $reasons = array_merge(self::$cancelReasons, self::$unlockReasons);
        $reasonDisplayText = array_search($this->getModifyReason(), $reasons);
        return !empty($reasonDisplayText) ? $reasonDisplayText : "Other; {$this->getModifyReason()}";
    }

    public function canUnlock(): bool
    {
        if (!empty($this->finalizedTs) &&
            $this->getModifyType() !== NphSample::CANCEL &&
            $this->getModifyType() !== NphSample::UNLOCK) {
            return true;
        }
        return false;
    }

    public function getRdrSampleObj(string $sampleIdentifier, string $description, array $samplesMetadata = []): array
    {
        $collectedTs = $this->getCollectedTs();
        $collectedTs->setTimezone(new \DateTimeZone('UTC'));
        $finalizedTs = $this->getFinalizedTs();
        $finalizedTs->setTimezone(new \DateTimeZone('UTC'));
        $sampleData = [
            'test' => $sampleIdentifier,
            'description' => $description,
            'collected' => $collectedTs->format('Y-m-d\TH:i:s\Z'),
            'finalized' => $finalizedTs->format('Y-m-d\TH:i:s\Z')
        ];
        if ($this->getNphOrder()->getOrderType() === NphOrder::TYPE_URINE || $this->getNphOrder()->getOrderType() === NphOrder::TYPE_24URINE) {
            $sampleData['color'] = $samplesMetadata['urineColor'] ?? null;
            $sampleData['clarity'] = $samplesMetadata['urineClarity'] ?? null;
        }
        if ($this->getNphOrder()->getOrderType() === NphOrder::TYPE_STOOL || $this->getNphOrder()->getOrderType() === NphOrder::TYPE_STOOL_2) {
            $sampleData['bowelMovement'] = $samplesMetadata['bowelType'] ?? null;
            $sampleData['bowelMovementQuality'] = $samplesMetadata['bowelQuality'] ?? null;
            if ($samplesMetadata['freezedTs']) {
                $freezedTs = new \DateTime();
                $freezedTs->setTimestamp($samplesMetadata['freezedTs']);
                $sampleData['freezed'] = $freezedTs->format('Y-m-d\TH:i:s\Z');
            }
        }
        return $sampleData;
    }

    public function getRdrAliquotsSampleObj(array $aliquotsInfo): array
    {
        $aliquotObj = [];
        $counter = [];
        foreach ($this->getNphAliquots() as $aliquot) {
            $counter[$aliquot->getAliquotCode()] = isset($counter[$aliquot->getAliquotCode()]) ? $counter[$aliquot->getAliquotCode()] + 1 : 0;
            $collectedTs = $aliquot->getAliquotTs();
            $collectedTs->setTimezone(new \DateTimeZone('UTC'));
            $aliquotsData = [
                'id' => $aliquot->getAliquotId(),
                'identifier' => $aliquotsInfo[$aliquot->getAliquotCode()]['identifier'],
                'container' => $aliquotsInfo[$aliquot->getAliquotCode()]['container'],
                'description' => $aliquotsInfo[$aliquot->getAliquotCode()]['description'],
                'volume' => $aliquot->getVolume(),
                'collected' => $collectedTs->format('Y-m-d\TH:i:s\Z'),
                'units' => self::RDR_MICROLITER_UNITS[$aliquot->getUnits()] ?? $aliquot->getUnits()
            ];
            $metadata = $aliquot->getAliquotMetadata();
            if (isset($metadata[$aliquot->getAliquotCode() . 'glycerolAdditiveVolume'])) {
                if (is_array($metadata[$aliquot->getAliquotCode() . 'glycerolAdditiveVolume'])) {
                    $glycerolVolume = $metadata[$aliquot->getAliquotCode() . 'glycerolAdditiveVolume'][$counter[$aliquot->getAliquotCode()]];
                } else {
                    $glycerolVolume = $metadata[$aliquot->getAliquotCode() . 'glycerolAdditiveVolume'];
                }
                $aliquotsData['glycerolAdditiveVolume'] =
                    ['units' => 'uL',
                    'volume' => $metadata[$aliquot->getAliquotCode() . 'glycerolAdditiveVolume']
                ];
                $aliquotsData['volume'] += $glycerolVolume / 1000;
            }
            if ($aliquot->getStatus()) {
                $aliquotsData['status'] = $aliquot->getStatus();
            }
            $aliquotObj[] = $aliquotsData;
        }
        return $aliquotObj;
    }

    public function getSampleGroup(): ?int
    {
        return $this->sampleGroup;
    }

    public function setSampleGroup($sampleGroup): void
    {
        $this->sampleGroup = $sampleGroup;
    }

    public function getSampleMetadataArray(): ?array
    {
        $sampleMetadata = json_decode($this->getSampleMetadata(), true);
        if ($sampleMetadata) {
            $sampleMetadata['urineColor'] = isset($sampleMetadata['urineColor']) ? array_search(
                $sampleMetadata['urineColor'],
                NphOrderForm::$urineColors
            ) : '';
            $sampleMetadata['urineClarity'] = isset($sampleMetadata['urineClarity']) ? array_search(
                $sampleMetadata['urineClarity'],
                NphOrderForm::$urineClarity
            ) : '';
        }
        return $sampleMetadata;
    }
}
