<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class IdVerificationImport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $file_name;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\User')]
    private $user;

    #[ORM\Column(type: 'string', length: 255)]
    private $site;

    #[ORM\Column(type: 'datetime')]
    private $createdTs;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private $importStatus = 0;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private $confirm = 0;

    #[ORM\OneToMany(targetEntity: IdVerificationImportRow::class, mappedBy: 'import')]
    private $idVerificationImportRows;
    public function __construct()
    {
        $this->idVerificationImportRows = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFileName(): ?string
    {
        return $this->file_name;
    }

    public function setFileName(string $file_name): self
    {
        $this->file_name = $file_name;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
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
     * @return Collection|IdVerificationImportRow[]
     */
    public function getIdVerificationImportRows(): Collection
    {
        return $this->idVerificationImportRows;
    }

    public function addIdVerificationImportRow(IdVerificationImportRow $idVerificationImportRow): self
    {
        if (!$this->idVerificationImportRows->contains($idVerificationImportRow)) {
            $this->idVerificationImportRows[] = $idVerificationImportRow;
            $idVerificationImportRow->setImport($this);
        }

        return $this;
    }

    public function removeIdVerificationImportRow(IdVerificationImportRow $idVerificationImportRow): self
    {
        if ($this->idVerificationImportRows->removeElement($idVerificationImportRow)) {
            // set the owning side to null (unless already changed)
            if ($idVerificationImportRow->getImport() === $this) {
                $idVerificationImportRow->setImport(null);
            }
        }

        return $this;
    }
}
