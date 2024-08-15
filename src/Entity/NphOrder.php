<?php

namespace App\Entity;

use App\Form\Nph\NphOrderForm;
use App\Repository\NphOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'nph_orders')]
#[ORM\UniqueConstraint(name: 'order_id', columns: ['order_id'])]
#[ORM\Entity(repositoryClass: NphOrderRepository::class)]
class NphOrder
{
    public const TYPE_BLOOD = 'blood';
    public const TYPE_STOOL = 'stool';
    public const TYPE_STOOL_2 = 'stool2';
    public const TYPE_URINE = 'urine';
    public const TYPE_24URINE = '24urine';
    public const TYPE_NAIL = 'nail';
    public const TYPE_HAIR = 'hair';
    public const TYPE_DLW = 'urineDlw';
    public const TYPE_MODULE_3_SALIVA = 'saliva3';
    public const VISIT_DISPLAY_NAME_MAPPER = [
        'LMT' => 'LMT',
        'Period1Diet' => 'Diet',
        'Period1DLW' => 'DLW',
        'Period1DSMT' => 'DSMT',
        'Period1LMT' => 'LMT',
        'Period2Diet' => 'Diet',
        'Period2DLW' => 'DLW',
        'Period2DSMT' => 'DSMT',
        'Period2LMT' => 'LMT',
        'Period3Diet' => 'Diet',
        'Period3DLW' => 'DLW',
        'Period3DSMT' => 'DSMT',
        'Period3LMT' => 'LMT',
    ];
    private const TIMEPOINT_DISPLAY_NAME_MAPPER = [
        'day0' => 'Day 0',
        'day2' => 'Day 2',
        'day12' => 'Day 12',
        'day0PreDoseA' => 'Day 0 Pre Dose A',
        'day1PreDoseB' => 'Day 1 Pre Dose B',
        'day1PostDoseC' => 'Day Post Dose C',
        'day1PostDoseD' => 'Day Post Dose D',
        'day6E' => 'Day 6 E',
        'day7F' => 'Day 7 F',
        'day13G' => 'Day 13 G',
        'day14F' => 'Day 14 F',
        'preDSMT' => 'Pre DSMT',
        'minus15min' => '-15 min',
        'minus5min' => '-5 min',
        '15min' => '15 min',
        '30min' => '30 min',
        '60min' => '60 min',
        '90min' => '90 min',
        '120min' => '120 min',
        '180min' => '180 min',
        '240min' => '240 min',
        'postDSMT' => 'Post DSMT',
        'preLMT' => 'Pre LMT',
        'postLMT' => 'Post LMT',
    ];
    private const TYPE_DISPLAY_OVERRIDE = [
        2 => [
            'urine' => 'Spot Urine'
        ],
        3 => [
            'urine' => 'Spot Urine',
            '24urine' => '24 Hour Urine',
            'urineDlw' => 'Urine DLW',
            'saliva3' => 'Saliva',
            'stool' => 'Stool Kit 1',
            'stool2' => 'Stool Kit 2'
        ],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 100)]
    private $orderId;

    #[ORM\Column(type: 'string', length: 50)]
    private $participantId;

    #[ORM\Column(type: 'string', length: 10)]
    private $module;

    #[ORM\Column(type: 'string', length: 50)]
    private $timepoint;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $visitType;

    #[ORM\Column(type: 'string', length: 50)]
    private $site;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $user;

    #[ORM\Column(type: 'datetime')]
    private $createdTs;

    #[ORM\OneToMany(targetEntity: NphSample::class, mappedBy: 'nphOrder')]
    private $nphSamples;

    #[ORM\Column(type: 'string', length: 20)]
    private $orderType;

    #[ORM\Column(type: 'text', nullable: true)]
    private $metadata;

    #[ORM\Column(type: 'string', length: 50)]
    private $biobankId;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $createdTimezoneId;

    #[ORM\Column]
    private bool $DowntimeGenerated = false;

    #[ORM\ManyToOne]
    private ?User $DowntimeGeneratedUser = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $downtimeGeneratedTs = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $visitPeriod = null;

    public function __construct()
    {
        $this->nphSamples = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getParticipantId(): ?string
    {
        return $this->participantId;
    }

    public function setParticipantId(string $participantId): self
    {
        $this->participantId = $participantId;

        return $this;
    }

    public function getModule(): ?string
    {
        return $this->module;
    }

    public function setModule(string $module): self
    {
        $this->module = $module;

        return $this;
    }

    public function getTimepoint(): ?string
    {
        return $this->timepoint;
    }

    public function setTimepoint(string $timepoint): self
    {
        $this->timepoint = $timepoint;

        return $this;
    }

    public function getVisitType(): ?string
    {
        return $this->visitType;
    }

    public function setVisitType(string $visitType): self
    {
        $this->visitType = $visitType;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCreatedTs(): ?\DateTime
    {
        return $this->createdTs;
    }

    public function setCreatedTs(\DateTime $createdTs): self
    {
        $this->createdTs = $createdTs;

        return $this;
    }

    /**
     * @return Collection|NphSample[]
     */
    public function getNphSamples(): Collection
    {
        return $this->nphSamples;
    }

    public function addNphSample(NphSample $nphSample): self
    {
        if (!$this->nphSamples->contains($nphSample)) {
            $this->nphSamples[] = $nphSample;
            $nphSample->setNphOrder($this);
        }

        return $this;
    }

    public function removeNphSample(NphSample $nphSample): self
    {
        if ($this->nphSamples->removeElement($nphSample)) {
            // set the owning side to null (unless already changed)
            if ($nphSample->getNphOrder() === $this) {
                $nphSample->setNphOrder(null);
            }
        }

        return $this;
    }

    public function getOrderType(): ?string
    {
        return $this->orderType;
    }

    public function setOrderType(string $orderType): self
    {
        $this->orderType = $orderType;

        return $this;
    }

    public function getMetadata(): ?string
    {
        return $this->metadata;
    }

    public function setMetadata(?string $metadata): self
    {
        $this->metadata = $metadata;

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

    public function canCancel(): bool
    {
        foreach ($this->getNphSamples() as $nphSample) {
            if ($nphSample->getModifyType() !== NphSample::CANCEL) {
                return true;
            }
        }
        return false;
    }

    public function canRestore(array $activeSamples = []): bool
    {
        foreach ($this->getNphSamples() as $nphSample) {
            if ($nphSample->getModifyType() === NphSample::CANCEL && !in_array($nphSample->getSampleCode(), $activeSamples, true)) {
                return true;
            }
        }
        return false;
    }

    public function canModify($type): bool
    {
        if ($type === NphSample::CANCEL) {
            return $this->canCancel();
        }
        if ($type === NphSample::RESTORE) {
            return $this->canRestore();
        }
        return false;
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

    public function getSampleGroupBySampleCode(string $sampleCode): ?string
    {
        foreach ($this->nphSamples as $nphSample) {
            if ($nphSample->getSampleCode() === $sampleCode) {
                return $nphSample->getSampleGroup();
            }
        }
        throw new \ErrorException("Sample group not found for SampleCode $sampleCode with SampleId $this->id");
    }

    public function isDisabled(): bool
    {
        foreach ($this->nphSamples as $nphSample) {
            if (empty($nphSample->getRdrId())) {
                return false;
            }
        }
        return true;
    }

    public function isMetadataFieldDisabled(): bool
    {
        foreach ($this->nphSamples as $nphSample) {
            if ($nphSample->getRdrId()) {
                return true;
            }
        }
        return false;
    }

    public function isFreezeTsDisabled(string|null $modifyType): bool
    {
        $atLeastOneSampleIsFinalized = false;
        foreach ($this->nphSamples as $nphSample) {
            if ($nphSample->getRdrId()) {
                $atLeastOneSampleIsFinalized = true;
                break;
            }
        }
        if ($atLeastOneSampleIsFinalized || $modifyType === NphSample::UNLOCK) {
            return empty($this->getMetadataArray()['freezedTs']);
        }
        return false;
    }

    public function getStatus(): string
    {
        $statusCount = [];
        $sampleCount = count($this->nphSamples);
        foreach ($this->nphSamples as $nphSample) {
            $sampleStatus = $nphSample->getStatus();
            $statusCount[$sampleStatus] = isset($statusCount[$sampleStatus]) ? $statusCount[$sampleStatus] + 1 : 1;
        }
        if (isset($statusCount['Canceled']) && $statusCount['Canceled'] === $sampleCount) {
            return 'Canceled';
        }
        if (isset($statusCount['Canceled'])) {
            $sampleCount = $sampleCount - $statusCount['Canceled'];
        }
        if (isset($statusCount['Finalized']) && $statusCount['Finalized'] === $sampleCount) {
            return 'Finalized';
        }
        if (isset($statusCount['Created']) && $statusCount['Created'] === $sampleCount) {
            return 'Created';
        }
        if (isset($statusCount['Collected']) && $statusCount['Collected'] === $sampleCount) {
            return 'Collected';
        }
        if (isset($statusCount['Biobank Finalized']) && $statusCount['Biobank Finalized'] === $sampleCount) {
            return 'Finalized';
        }
        if ((isset($statusCount['Finalized']) && isset($statusCount['Biobank Finalized'])) && ($statusCount['Finalized'] + $statusCount['Biobank Finalized'] === $sampleCount)) {
            return 'Finalized';
        }
        return 'In Progress';
    }

    public function getCollectedTs(): ?\DateTime
    {
        foreach ($this->nphSamples as $nphSample) {
            if ($nphSample->getCollectedTs()) {
                return $nphSample->getCollectedTs();
            }
        }
        return null;
    }

    public function isStoolCollectedTsDisabled(): bool
    {
        if ($this->getOrderType() === self::TYPE_STOOL) {
            foreach ($this->nphSamples as $nphSample) {
                if ($nphSample->getRdrId()) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getMetadataArray(): ?array
    {
        $metadata = json_decode($this->getMetadata(), true);
        if ($metadata) {
            $metadata['bowelType'] = isset($metadata['bowelType']) ? array_search(
                $metadata['bowelType'],
                NphOrderForm::$bowelMovements
            ) : '';
            $metadata['bowelQuality'] = isset($metadata['bowelQuality']) ? array_search(
                $metadata['bowelQuality'],
                NphOrderForm::$bowelMovementQuality
            ) : '';
        }
        return $metadata;
    }

    public function getOrderTypeDisplayName(): string
    {
        if (isset($this::TYPE_DISPLAY_OVERRIDE[$this->getModule()][$this->getOrderType()])) {
            return $this::TYPE_DISPLAY_OVERRIDE[$this->getModule()][$this->getOrderType()];
        }
        return ucfirst($this->getOrderType());
    }

    public function isDowntimeGenerated(): bool
    {
        return $this->DowntimeGenerated;
    }

    public function setDowntimeGenerated(bool $DowntimeGenerated): static
    {
        $this->DowntimeGenerated = $DowntimeGenerated;

        return $this;
    }

    public function getDowntimeGeneratedUser(): ?User
    {
        return $this->DowntimeGeneratedUser;
    }

    public function setDowntimeGeneratedUser(?User $DowntimeGeneratedUser): static
    {
        $this->DowntimeGeneratedUser = $DowntimeGeneratedUser;

        return $this;
    }

    public function getDowntimeGeneratedTs(): ?\DateTimeInterface
    {
        return $this->downtimeGeneratedTs;
    }

    public function setDowntimeGeneratedTs(?\DateTimeInterface $downtimeGeneratedTs): static
    {
        $this->downtimeGeneratedTs = $downtimeGeneratedTs;

        return $this;
    }

    public function getVisitPeriod(): ?string
    {
        return $this->visitPeriod;
    }

    public function setVisitPeriod(?string $visitPeriod): static
    {
        $this->visitPeriod = $visitPeriod;

        return $this;
    }

    public function getVisitDisplayName(): ?string
    {
        return self::VISIT_DISPLAY_NAME_MAPPER[$this->visitPeriod];
    }

    public function getTimepointDisplayName(): ?string
    {
        return self::TIMEPOINT_DISPLAY_NAME_MAPPER[$this->timepoint];
    }
}
