<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class IncentiveImport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $fileName;

    #[ORM\OneToOne(targetEntity: 'App\Entity\User')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $site;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdTs;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $importStatus = 0;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $confirm = 0;

    /** @var Collection<int, IncentiveImportRow> */
    #[ORM\OneToMany(targetEntity: IncentiveImportRow::class, mappedBy: 'import', cascade: ['persist', 'remove'])]
    private Collection $incentiveImportRows;
    public function __construct()
    {
        $this->incentiveImportRows = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;

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

    public function getCreatedTs(): ?\DateTimeInterface
    {
        return $this->createdTs;
    }

    public function setCreatedTs(\DateTimeInterface $createdTs): self
    {
        $this->createdTs = $createdTs;

        return $this;
    }

    public function getImportStatus(): ?int
    {
        return $this->importStatus;
    }

    public function setImportStatus(int $importStatus): self
    {
        $this->importStatus = $importStatus;

        return $this;
    }

    public function getConfirm(): ?int
    {
        return $this->confirm;
    }

    public function setConfirm(int $confirm): self
    {
        $this->confirm = $confirm;

        return $this;
    }

    /**
     * @return Collection<int, IncentiveImportRow>
     */
    public function getIncentiveImportRows(): Collection
    {
        return $this->incentiveImportRows;
    }

    public function addIncentiveImportRow(IncentiveImportRow $incentiveImportRow): self
    {
        if (!$this->incentiveImportRows->contains($incentiveImportRow)) {
            $this->incentiveImportRows[] = $incentiveImportRow;
            $incentiveImportRow->setImport($this);
        }

        return $this;
    }

    public function removeIncentiveImportRow(IncentiveImportRow $incentiveImportRow): self
    {
        if ($this->incentiveImportRows->contains($incentiveImportRow)) {
            $this->incentiveImportRows->removeElement($incentiveImportRow);
        }

        return $this;
    }
}
