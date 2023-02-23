<?php

namespace App\Entity;

use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="problems")
 * @ORM\Entity(repositoryClass="App\Repository\ProblemRepository")
 */
class Problem
{
    public const RELATED_BASELINE = 'related_baseline';
    public const UNRELATED_BASELINE = 'unrelated_baseline';
    public const OTHER = 'other';
    public const PROBLEM_TYPE_OPTIONS = ['Physical injury related to baseline appointment', 'Physical injury unrelated to baseline appointment', 'Other'];

    /**
     * @var int
     *
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    private $userId;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=50, nullable=false)
     */
    private $site;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=50, nullable=false)
     */
    private $participantId;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $problemType;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $enrollmentSite;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $staffName;

    /**
     * @var ?DateTimeInterface
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $problemDate;

    /**
     * @var ?DateTimeInterface
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $providerAwareDate;

    /**
     * @var string|null
     *
     * @ORM\Column(type="text", length=65535, nullable=true)
     */
    private $description;

    /**
     * @var string|null
     *
     * @ORM\Column(type="text", length=65535, nullable=true)
     */
    private $actionTaken;

    /**
     * @var int|null
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    private $finalizedUserId;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $finalizedSite;

    /**
     * @var ?DateTimeInterface
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $finalizedTs;

    /**
     * @var DateTimeInterface
     *
     * @ORM\Column(type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $createdTs;

    /**
     * @var DateTimeInterface
     *
     * @ORM\Column(type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $updatedTs;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ProblemComment", mappedBy="problem")
     */
    private $problemComments;

    public function __construct()
    {
        $this->createdTs = new \DateTime();
        $this->updatedTs = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

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

    public function getParticipantId(): ?string
    {
        return $this->participantId;
    }

    public function setParticipantId(string $participantId): self
    {
        $this->participantId = $participantId;

        return $this;
    }

    public function getProblemType(): ?string
    {
        return $this->problemType;
    }

    public function setProblemType(?string $problemType): self
    {
        $this->problemType = $problemType;

        return $this;
    }

    public function getEnrollmentSite(): ?string
    {
        return $this->enrollmentSite;
    }

    public function setEnrollmentSite(?string $enrollmentSite): self
    {
        $this->enrollmentSite = $enrollmentSite;

        return $this;
    }

    public function getStaffName(): ?string
    {
        return $this->staffName;
    }

    public function setStaffName(?string $staffName): self
    {
        $this->staffName = $staffName;

        return $this;
    }

    public function getProblemDate(): ?DateTimeInterface
    {
        return $this->problemDate;
    }

    public function setProblemDate(?DateTimeInterface $problemDate): self
    {
        $this->problemDate = $problemDate;

        return $this;
    }

    public function getProviderAwareDate(): ?DateTimeInterface
    {
        return $this->providerAwareDate;
    }

    public function setProviderAwareDate(?DateTimeInterface $providerAwareDate): self
    {
        $this->providerAwareDate = $providerAwareDate;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getActionTaken(): ?string
    {
        return $this->actionTaken;
    }

    public function setActionTaken(?string $actionTaken): self
    {
        $this->actionTaken = $actionTaken;

        return $this;
    }

    public function getFinalizedUserId(): ?int
    {
        return $this->finalizedUserId;
    }

    public function setFinalizedUserId(?int $finalizedUserId): self
    {
        $this->finalizedUserId = $finalizedUserId;

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

    public function getFinalizedTs(): ?DateTimeInterface
    {
        return $this->finalizedTs;
    }

    public function setFinalizedTs(?DateTimeInterface $finalizedTs): self
    {
        $this->finalizedTs = $finalizedTs;

        return $this;
    }

    public function getCreatedTs(): ?DateTimeInterface
    {
        return $this->createdTs;
    }

    public function setCreatedTs(?DateTimeInterface  $createdTs): self
    {
        $this->createdTs = $createdTs;

        return $this;
    }

    public function getUpdatedTs(): ?DateTimeInterface
    {
        return $this->updatedTs;
    }

    public function setUpdatedTs(?DateTimeInterface $updatedTs): self
    {
        $this->updatedTs = $updatedTs;

        return $this;
    }

    /**
     * @return Collection|ProblemComment[]
     */
    public function getProblemComments(): Collection
    {
        return $this->problemComments;
    }

    public function addProblemComment(ProblemComment $problemComment): self
    {
        if (!$this->problemComments->contains($problemComment)) {
            $this->problemComments[] = $problemComment;
            $problemComment->setProblem($this);
        }

        return $this;
    }

    public function removeProblemComment(ProblemComment $problemComment): self
    {
        if ($this->problemComments->contains($problemComment)) {
            $this->problemComments->removeElement($problemComment);
            // set the owning side to null (unless already changed)
            if ($problemComment->getProblem() === $this) {
                $problemComment->setProblem(null);
            }
        }

        return $this;
    }
}
