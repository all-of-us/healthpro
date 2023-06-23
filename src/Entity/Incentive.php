<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\IncentiveRepository')]
class Incentive
{
    public const CREATE = 'create';
    public const AMEND = 'amend';
    public const CANCEL = 'cancel';
    public const CASH = 'cash';
    public const GIFT_CARD = 'gift_card';
    public const VOUCHER = 'voucher';
    public const PROMOTIONAL = 'promotional';
    public const ONE_TIME = 'one_time';
    public const REDRAW = 'redraw';
    public const OTHER = 'other';

    public static $incentiveTypeChoices = [
        'Cash' => self::CASH,
        'Gift Card' => self::GIFT_CARD,
        'Voucher' => self::VOUCHER,
        'Promotional Item' => self::PROMOTIONAL,
        'Other' => self::OTHER
    ];

    public static $incentiveOccurrenceChoices = [
        'One-time Incentive' => self::ONE_TIME,
        'Redraw' => self::REDRAW,
        'Other' => self::OTHER
    ];

    public static $incentiveAmountChoices = [
        '$25.00' => '25',
        '$15.00' => '15',
        'Other' => self::OTHER
    ];

    public static $giftCardTypes = [
        'ClinCard',
        'Target',
        'Safeway',
        'Kroger',
        'Walmart',
        'Walmart Gas',
        'Food City',
        'Stop & Shop',
        'Dunkin Donuts',
        'Visa',
        'MasterCard',
        'Amazon',
        'Meijer',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 50)]
    private $participantId;

    #[ORM\OneToOne(targetEntity: 'App\Entity\User')]
    private $user;

    #[ORM\Column(type: 'string', length: 50)]
    private $site;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $incentiveDateGiven;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $incentiveType;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $otherIncentiveType;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $incentiveOccurrence;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $otherIncentiveOccurrence;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $incentiveAmount;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $giftCardType;

    #[ORM\Column(type: 'text', nullable: true)]
    private $notes;

    #[ORM\Column(type: 'datetime')]
    private $createdTs;

    #[ORM\OneToOne(targetEntity: 'App\Entity\User', cascade: ['persist', 'remove'])]
    private $amendedUser;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $amendedTs;

    #[ORM\OneToOne(targetEntity: 'App\Entity\User', cascade: ['persist', 'remove'])]
    private $cancelledUser;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $cancelledTs;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $rdrId;

    #[ORM\Column(type: 'boolean')]
    private $declined;

    #[ORM\ManyToOne(targetEntity: IncentiveImport::class, inversedBy: 'incentives')]
    private $import;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getIncentiveDateGiven(): ?\DateTimeInterface
    {
        return $this->incentiveDateGiven;
    }

    public function setIncentiveDateGiven(?\DateTimeInterface $incentiveDateGiven): self
    {
        $this->incentiveDateGiven = $incentiveDateGiven;

        return $this;
    }

    public function getIncentiveType(): ?string
    {
        return $this->incentiveType;
    }

    public function setIncentiveType(?string $incentiveType): self
    {
        $this->incentiveType = $incentiveType;

        return $this;
    }

    public function getOtherIncentiveType(): ?string
    {
        return $this->otherIncentiveType;
    }

    public function setOtherIncentiveType(?string $otherIncentiveType): self
    {
        $this->otherIncentiveType = $otherIncentiveType;

        return $this;
    }

    public function getIncentiveOccurrence(): ?string
    {
        return $this->incentiveOccurrence;
    }

    public function setIncentiveOccurrence(?string $incentiveOccurrence): self
    {
        $this->incentiveOccurrence = $incentiveOccurrence;

        return $this;
    }

    public function getOtherIncentiveOccurrence(): ?string
    {
        return $this->otherIncentiveOccurrence;
    }

    public function setOtherIncentiveOccurrence(?string $otherIncentiveOccurrence): self
    {
        $this->otherIncentiveOccurrence = $otherIncentiveOccurrence;

        return $this;
    }

    public function getIncentiveAmount(): ?string
    {
        return $this->incentiveAmount;
    }

    public function setIncentiveAmount(?string $incentiveAmount): self
    {
        $this->incentiveAmount = $incentiveAmount;

        return $this;
    }

    public function getGiftCardType(): ?string
    {
        return $this->giftCardType;
    }

    public function setGiftCardType(?string $giftCardType): self
    {
        $this->giftCardType = $giftCardType;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

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

    public function getAmendedUser(): ?User
    {
        return $this->amendedUser;
    }

    public function setAmendedUser(?User $amendedUser): self
    {
        $this->amendedUser = $amendedUser;

        return $this;
    }

    public function getAmendedTs(): ?\DateTimeInterface
    {
        return $this->amendedTs;
    }

    public function setAmendedTs(?\DateTimeInterface $amendedTs): self
    {
        $this->amendedTs = $amendedTs;

        return $this;
    }

    public function getCancelledUser(): ?User
    {
        return $this->cancelledUser;
    }

    public function setCancelledUser(?User $cancelledUser): self
    {
        $this->cancelledUser = $cancelledUser;

        return $this;
    }

    public function getCancelledTs(): ?\DateTimeInterface
    {
        return $this->cancelledTs;
    }

    public function setCancelledTs(?\DateTimeInterface $cancelledTs): self
    {
        $this->cancelledTs = $cancelledTs;

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

    public function getDeclined(): ?bool
    {
        return $this->declined;
    }

    public function setDeclined(int $status): self
    {
        $this->declined = $status;

        return $this;
    }

    public function getIncentiveTypeDisplayName()
    {
        return array_search($this->incentiveType, Incentive::$incentiveTypeChoices);
    }

    public function getIncentiveOccurrenceDisplayName()
    {
        return array_search($this->incentiveOccurrence, Incentive::$incentiveOccurrenceChoices);
    }

    public function getIncentiveAmountDisplayName()
    {
        return array_search($this->incentiveAmount, Incentive::$incentiveAmountChoices);
    }

    public function getImport(): ?IncentiveImport
    {
        return $this->import;
    }

    public function setImport(?IncentiveImport $import): self
    {
        $this->import = $import;

        return $this;
    }
}
