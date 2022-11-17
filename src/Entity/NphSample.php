<?php

namespace App\Entity;

use App\Repository\NphSampleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=NphSampleRepository::class)
 * @ORM\Table(name="nph_samples", uniqueConstraints={
 *   @ORM\UniqueConstraint(name="sample_id", columns={"sample_id"})
 * })
 */
class NphSample
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $sampleId;

    /**
     * @ORM\ManyToOne(targetEntity=NphOrder::class, inversedBy="nphSamples")
     * @ORM\JoinColumn(nullable=false)
     */
    private $nphOrder;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $sampleCode;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $sampleMetadata;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $collectedSite;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     */
    private $collectedUser;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $collectedTs;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $collectedNotes;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $finalizedSite;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     */
    private $finalizedUser;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $finalizedTs;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $finalizedNotes;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $rdrId;

    /**
     * @ORM\OneToMany(targetEntity=NphAliquot::class, mappedBy="nphSample")
     */
    private $nphAliquots;

    public function __construct()
    {
        $this->nphAliquots = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSampleId(): ?string
    {
        return $this->sampleId;
    }

    public function setSampleId(string $sampleId): self
    {
        $this->sampleId = $sampleId;

        return $this;
    }

    public function getNphOrder(): ?NphOrder
    {
        return $this->nphOrder;
    }

    public function setNphOrder(?NphOrder $nphOrder): self
    {
        $this->nphOrder = $nphOrder;
        // This loads collection data in phpunit
        $nphOrder->addNphSample($this);
        return $this;
    }

    public function getSampleCode(): ?string
    {
        return $this->sampleCode;
    }

    public function setSampleCode(string $sampleCode): self
    {
        $this->sampleCode = $sampleCode;

        return $this;
    }

    public function getSampleMetadata(): ?string
    {
        return $this->sampleMetadata;
    }

    public function setSampleMetadata(?string $sampleMetadata): self
    {
        $this->sampleMetadata = $sampleMetadata;

        return $this;
    }

    public function getCollectedSite(): ?string
    {
        return $this->collectedSite;
    }

    public function setCollectedSite(?string $collectedSite): self
    {
        $this->collectedSite = $collectedSite;

        return $this;
    }

    public function getCollectedUser(): ?User
    {
        return $this->collectedUser;
    }

    public function setCollectedUser(?User $collectedUser): self
    {
        $this->collectedUser = $collectedUser;

        return $this;
    }

    public function getCollectedTs(): ?\DateTimeInterface
    {
        return $this->collectedTs;
    }

    public function setCollectedTs(?\DateTimeInterface $collectedTs): self
    {
        $this->collectedTs = $collectedTs;

        return $this;
    }

    public function getCollectedNotes(): ?string
    {
        return $this->collectedNotes;
    }

    public function setCollectedNotes(?string $collectedNotes): self
    {
        $this->collectedNotes = $collectedNotes;

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

    public function getFinalizedUser(): ?User
    {
        return $this->finalizedUser;
    }

    public function setFinalizedUser(?User $finalizedUser): self
    {
        $this->finalizedUser = $finalizedUser;

        return $this;
    }

    public function getFinalizedTs(): ?\DateTimeInterface
    {
        return $this->finalizedTs;
    }

    public function setFinalizedTs(?\DateTimeInterface $finalizedTs): self
    {
        $this->finalizedTs = $finalizedTs;

        return $this;
    }

    public function getFinalizedNotes(): ?string
    {
        return $this->finalizedNotes;
    }

    public function setFinalizedNotes(?string $finalizedNotes): self
    {
        $this->finalizedNotes = $finalizedNotes;

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

    /**
     * @return Collection|NphAliquot[]
     */
    public function getNphAliquots(): Collection
    {
        return $this->nphAliquots;
    }

    public function addNphAliquot(NphAliquot $nphAliquot): self
    {
        if (!$this->nphAliquots->contains($nphAliquot)) {
            $this->nphAliquots[] = $nphAliquot;
            $nphAliquot->setNphSample($this);
        }

        return $this;
    }

    public function removeNphAliquot(NphAliquot $nphAliquot): self
    {
        if ($this->nphAliquots->removeElement($nphAliquot)) {
            // set the owning side to null (unless already changed)
            if ($nphAliquot->getNphSample() === $this) {
                $nphAliquot->setNphSample(null);
            }
        }

        return $this;
    }
}
