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
    public const ADULT_PARTICIPANT = 'adult_participant';
    public const PEDIATRIC_GUARDIAN = 'pediatric_guardian';
    public const PEDIATRIC_PARTICIPANT = 'pediatric_participant';
    public const ITEM_OF_APPRECIATION = 'item_of_appreciation';
    public const PEDIATRIC_VISIT = 'pediatric_visit';

    /** @var array<string, string> */
    public static array $incentiveTypeChoices = [
        'Cash' => self::CASH,
        'Gift Card' => self::GIFT_CARD,
        'Voucher' => self::VOUCHER,
        'Promotional Item' => self::PROMOTIONAL,
        'Item of Appreciation' => self::ITEM_OF_APPRECIATION,
        'Other' => self::OTHER,
    ];

    /** @var array<string, string> */
    public static array $incentiveOccurrenceChoices = [
        'One-time Incentive' => self::ONE_TIME,
        'Redraw' => self::REDRAW,
        'Other' => self::OTHER,
        'Pediatric Visit' => self::PEDIATRIC_VISIT,
    ];

    /** @var array<string, string> */
    public static array $incentiveAmountChoices = [
        '$25.00' => '25',
        '$15.00' => '15',
        'Other' => self::OTHER
    ];

    /** @var list<string> */
    public static array $giftCardTypes = [
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

    /** @var array<string, string> */
    public static array $recipientChoices = [
        'Adult Participant' => self::ADULT_PARTICIPANT,
        'Pediatric Guardian' => self::PEDIATRIC_GUARDIAN,
        'Pediatric Participant' => self::PEDIATRIC_PARTICIPANT,
        'Other' => self::OTHER,
    ];

    /** @var list<string> */
    public static array $itemTypes = [
        'Candy',
        'Pencil',
        'Pen',
        'Toy'
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $participantId;

    #[ORM\OneToOne(targetEntity: 'App\Entity\User')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $site;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $incentiveDateGiven = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $incentiveType = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $otherIncentiveType = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $incentiveOccurrence = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $otherIncentiveOccurrence = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $incentiveAmount = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $giftCardType = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdTs;

    #[ORM\OneToOne(targetEntity: 'App\Entity\User', cascade: ['persist', 'remove'])]
    private ?User $amendedUser = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $amendedTs = null;

    #[ORM\OneToOne(targetEntity: 'App\Entity\User', cascade: ['persist', 'remove'])]
    private ?User $cancelledUser = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $cancelledTs = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $rdrId = null;

    #[ORM\Column(type: 'boolean')]
    private bool $declined;

    #[ORM\ManyToOne(targetEntity: IncentiveImport::class, inversedBy: 'incentives')]
    private ?IncentiveImport $import = null;

    #[ORM\Column(length: 255)]
    private string $Recipient;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $typeOfItem = null;

    #[ORM\Column(nullable: true)]
    private ?int $numberOfItems = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $relatedParticipantRecipient = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipantId(): string
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

    public function getSite(): string
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
        return $this->incentiveAmount !== null ? (string) $this->incentiveAmount : null;
    }

    public function setIncentiveAmount(int|string|null $incentiveAmount): self
    {
        $this->incentiveAmount = $incentiveAmount !== null ? (int) $incentiveAmount : null;

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

    public function getCreatedTs(): \DateTimeInterface
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

    public function setRdrId(?string $rdrId): self
    {
        $this->rdrId = $rdrId;

        return $this;
    }

    public function getDeclined(): bool
    {
        return $this->declined;
    }

    public function setDeclined(bool|int $status): self
    {
        $this->declined = (bool) $status;

        return $this;
    }

    public function getIncentiveTypeDisplayName(): ?string
    {
        return array_search($this->incentiveType, Incentive::$incentiveTypeChoices);
    }

    public function getIncentiveOccurrenceDisplayName(): ?string
    {
        return array_search($this->incentiveOccurrence, Incentive::$incentiveOccurrenceChoices);
    }

    public function getIncentiveAmountDisplayName(): ?string
    {
        return array_search($this->incentiveAmount, Incentive::$incentiveAmountChoices);
    }

    public function getIncentiveRecipientDisplayName(): ?string
    {
        if ($this->getOtherIncentiveRecipient()) {
            return 'Other';
        }
        return array_search($this->Recipient, Incentive::$recipientChoices);
    }

    public function getOtherIncentiveRecipient(): ?string
    {
        $pos = strpos($this->Recipient, 'other,');
        if ($pos !== false) {
            return preg_replace('/other, /', '', $this->Recipient, 1);
        }
        return null;
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

    public function getRecipient(): ?string
    {
        return $this->Recipient;
    }

    public function setRecipient(string $Recipient): static
    {
        $this->Recipient = $Recipient;

        return $this;
    }

    public function getTypeOfItem(): ?string
    {
        return $this->typeOfItem;
    }

    public function setTypeOfItem(?string $typeOfItem): static
    {
        $this->typeOfItem = $typeOfItem;

        return $this;
    }

    public function getNumberOfItems(): ?int
    {
        return $this->numberOfItems;
    }

    public function setNumberOfItems(?int $numberOfItems): static
    {
        $this->numberOfItems = $numberOfItems;

        return $this;
    }

    /** @return array<string, string> */
    public static function getIncentiveOptions(bool $isPediatricParticipant = false): array
    {
        $choices = self::$incentiveTypeChoices;
        if (!$isPediatricParticipant) {
            unset($choices['Item of Appreciation']);
        }
        return $choices;
    }

    /** @return array<string, string> */
    public static function getIncentiveOccurenceOptions(bool $isPediatricParticipant = false): array
    {
        $choices = self::$incentiveOccurrenceChoices;
        if (!$isPediatricParticipant) {
            unset($choices['Pediatric Visit']);
        }
        return $choices;
    }

    public function getRelatedParticipantRecipient(): ?string
    {
        return $this->relatedParticipantRecipient;
    }

    public function setRelatedParticipantRecipient(?string $relatedParticipantRecipient): static
    {
        $this->relatedParticipantRecipient = $relatedParticipantRecipient;

        return $this;
    }
}
