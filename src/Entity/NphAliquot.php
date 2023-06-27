<?php

namespace App\Entity;

use App\Repository\NphAliquotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'nph_aliquots')]
#[ORM\UniqueConstraint(name: 'aliquot_id', columns: ['aliquot_id'])]
#[ORM\Entity(repositoryClass: NphAliquotRepository::class)]
class NphAliquot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 100)]
    private $aliquotId;

    #[ORM\ManyToOne(targetEntity: NphSample::class, inversedBy: 'nphAliquots')]
    #[ORM\JoinColumn(nullable: false)]
    private $nphSample;

    #[ORM\Column(type: 'datetime')]
    private $aliquotTs;

    #[ORM\Column(type: 'string', length: 100)]
    private $aliquotCode;

    #[ORM\Column(type: 'float', nullable: true)]
    private $volume;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private $units;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $status;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $aliquotTimezoneId;

    #[ORM\Column(type: 'json', nullable: true)]
    private $aliquotMetadata = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAliquotId(): ?string
    {
        return $this->aliquotId;
    }

    public function setAliquotId(string $aliquotId): self
    {
        $this->aliquotId = $aliquotId;

        return $this;
    }

    public function getNphSample(): ?NphSample
    {
        return $this->nphSample;
    }

    public function setNphSample(?NphSample $nphSample): self
    {
        $this->nphSample = $nphSample;
        $nphSample->addNphAliquot($this);
        return $this;
    }

    public function getAliquotTs(): ?\DateTime
    {
        return $this->aliquotTs;
    }

    public function setAliquotTs(\DateTime $aliquotTs): self
    {
        $this->aliquotTs = $aliquotTs;

        return $this;
    }

    public function getAliquotCode(): ?string
    {
        return $this->aliquotCode;
    }

    public function setAliquotCode(string $aliquotCode): self
    {
        $this->aliquotCode = $aliquotCode;

        return $this;
    }

    public function getVolume(): ?float
    {
        return $this->volume;
    }

    public function setVolume(?float $volume): self
    {
        $this->volume = $volume;

        return $this;
    }

    public function getUnits(): ?string
    {
        return $this->units;
    }

    public function setUnits(?string $units): self
    {
        $this->units = $units;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getAliquotTimezoneId(): ?int
    {
        return $this->aliquotTimezoneId;
    }

    public function setAliquotTimezoneId(?int $aliquotTimezoneId): self
    {
        $this->aliquotTimezoneId = $aliquotTimezoneId;

        return $this;
    }

    public function getAliquotMetadata(): ?array
    {
        return $this->aliquotMetadata;
    }

    public function setAliquotMetadata(?array $aliquotMetadata): self
    {
        $this->aliquotMetadata = $aliquotMetadata;

        return $this;
    }
}
