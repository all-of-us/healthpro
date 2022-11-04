<?php

namespace App\Entity;

use App\Repository\NphAliquotRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=NphAliquotRepository::class)
 * @ORM\Table(name="nph_aliquots", uniqueConstraints={
 *   @ORM\UniqueConstraint(name="aliquot_id", columns={"aliquot_id"})
 * })
 */
class NphAliquot
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
    private $aliquotId;

    /**
     * @ORM\ManyToOne(targetEntity=NphSample::class, inversedBy="nphAliquots")
     * @ORM\JoinColumn(nullable=false)
     */
    private $nphSample;

    /**
     * @ORM\Column(type="datetime")
     */
    private $aliquotTs;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $type;

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

        return $this;
    }

    public function getAliquotTs(): ?\DateTimeInterface
    {
        return $this->aliquotTs;
    }

    public function setAliquotTs(\DateTimeInterface $aliquotTs): self
    {
        $this->aliquotTs = $aliquotTs;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }
}
