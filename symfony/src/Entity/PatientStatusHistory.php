<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PatientStatusHistoryRepository")
 */
class PatientStatusHistory
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $user_id;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $site;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $status;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comments;

    /**
     * @ORM\Column(type="datetime")
     */
    private $created_ts;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $rdr_ts;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\PatientStatus", inversedBy="patientStatusHistories")
     * @ORM\JoinColumn(nullable=false)
     */
    private $patient_status;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\PatientStatusImport", inversedBy="patientStatusHistories")
     */
    private $import;

    /**
     * @ORM\Column(type="smallint", options={"default":0})
     */
    private $rdr_status = 0;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\PatientStatus", mappedBy="history", cascade={"persist", "remove"})
     */
    private $patientStatus;

    /**
     * RDR status
     * 1 = success
     * 2 = RDR 400 (Invalid participant id)
     * 3 = RDR 500 (Invalid patient status and other RDR 500 errors)
     * 4 = Other RDR errors
     */

    const STATUS_1 = 1;
    const STATUS_2 = 2;
    const STATUS_3 = 3;
    const STATUS_4 = 4;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): self
    {
        $this->user_id = $user_id;

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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function setComments(?string $comments): self
    {
        $this->comments = $comments;

        return $this;
    }

    public function getCreatedTs(): ?\DateTimeInterface
    {
        return $this->created_ts;
    }

    public function setCreatedTs(\DateTimeInterface $created_ts): self
    {
        $this->created_ts = $created_ts;

        return $this;
    }

    public function getRdrTs(): ?\DateTimeInterface
    {
        return $this->rdr_ts;
    }

    public function setRdrTs(?\DateTimeInterface $rdr_ts): self
    {
        $this->rdr_ts = $rdr_ts;

        return $this;
    }

    public function getPatientStatus(): ?PatientStatus
    {
        return $this->patient_status;
    }

    public function setPatientStatus(?PatientStatus $patient_status): self
    {
        $this->patient_status = $patient_status;

        return $this;
    }

    public function getImport(): ?PatientStatusImport
    {
        return $this->import;
    }

    public function setImport(?PatientStatusImport $import): self
    {
        $this->import = $import;

        return $this;
    }

    public function getRdrStatus(): ?int
    {
        return $this->rdr_status;
    }

    public function setRdrStatus(int $rdr_status): self
    {
        $this->rdr_status = $rdr_status;

        return $this;
    }
}
