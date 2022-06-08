<?php

namespace App\Entity;

use App\Repository\IdVerificationImportRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class IdVerificationImport
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $file_name;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     */
    private $user;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $site;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdTs;

    /**
     * @ORM\Column(type="smallint")
     */
    private $importStatus;

    /**
     * @ORM\Column(type="smallint")
     */
    private $confirm;

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
}
