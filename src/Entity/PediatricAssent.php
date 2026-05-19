<?php

namespace App\Entity;

use App\Repository\PediatricAssentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'pediatric_assent')]
#[ORM\Index(columns: ['participant_id'], name: 'participant_id')]
#[ORM\Index(columns: ['api_status'], name: 'api_status')]
#[ORM\Entity(repositoryClass: PediatricAssentRepository::class)]
class PediatricAssent
{
    public const TYPE_PHYSICAL_MEASUREMENT = 'PHYSICAL_MEASUREMENT';
    public const TYPE_BLOOD_SAMPLE = 'BLOOD_SAMPLE';
    public const TYPE_SALIVA_SAMPLE = 'SALIVA_SAMPLE';
    public const TYPE_URINE_SAMPLE = 'URINE_SAMPLE';

    public const RESPONSE_YES = 'YES';
    public const RESPONSE_NO = 'NO';
    public const RESPONSE_UNABLE_TO_ASSENT = 'UNABLE_TO_ASSENT';

    public const API_STATUS_PENDING = 'PENDING';
    public const API_STATUS_CREATED = 'CREATED';
    public const API_STATUS_FAILED = 'FAILED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $participantId;

    #[ORM\ManyToOne]
    private ?User $user = null;

    #[ORM\ManyToOne]
    private ?Measurement $measurement = null;

    #[ORM\ManyToOne]
    private ?Order $order = null;

    #[ORM\Column(length: 255)]
    private string $createdBy;

    #[ORM\Column(length: 50)]
    private string $site;

    #[ORM\Column(length: 50)]
    private string $assentType;

    #[ORM\Column(length: 50)]
    private string $assentResponse;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdTs;

    #[ORM\Column]
    private int $createdTimezoneId;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $apiAssentId = null;

    #[ORM\Column(length: 20)]
    private string $apiStatus;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $apiError = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipantId(): ?string
    {
        return $this->participantId;
    }

    public function setParticipantId(string $participantId): static
    {
        $this->participantId = $participantId;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getMeasurement(): ?Measurement
    {
        return $this->measurement;
    }

    public function setMeasurement(?Measurement $measurement): static
    {
        $this->measurement = $measurement;

        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(string $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getSite(): ?string
    {
        return $this->site;
    }

    public function setSite(string $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function getAssentType(): ?string
    {
        return $this->assentType;
    }

    public function setAssentType(string $assentType): static
    {
        $this->assentType = $assentType;

        return $this;
    }

    public function getAssentResponse(): ?string
    {
        return $this->assentResponse;
    }

    public function setAssentResponse(string $assentResponse): static
    {
        $this->assentResponse = $assentResponse;

        return $this;
    }

    public function getCreatedTs(): ?\DateTimeInterface
    {
        return $this->createdTs;
    }

    public function setCreatedTs(\DateTimeInterface $createdTs): static
    {
        $this->createdTs = $createdTs;

        return $this;
    }

    public function getCreatedTimezoneId(): ?int
    {
        return $this->createdTimezoneId;
    }

    public function setCreatedTimezoneId(int $createdTimezoneId): static
    {
        $this->createdTimezoneId = $createdTimezoneId;

        return $this;
    }

    public function getApiAssentId(): ?string
    {
        return $this->apiAssentId;
    }

    public function setApiAssentId(?string $apiAssentId): static
    {
        $this->apiAssentId = $apiAssentId;

        return $this;
    }

    public function getApiStatus(): ?string
    {
        return $this->apiStatus;
    }

    public function setApiStatus(string $apiStatus): static
    {
        $this->apiStatus = $apiStatus;

        return $this;
    }

    public function getApiError(): ?string
    {
        return $this->apiError;
    }

    public function setApiError(?string $apiError): static
    {
        $this->apiError = $apiError;

        return $this;
    }
}
