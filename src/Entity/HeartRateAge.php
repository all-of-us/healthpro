<?php

namespace App\Entity;

use App\Repository\HeartRateAgeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HeartRateAgeRepository::class)]
class HeartRateAge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $startAge;

    #[ORM\Column]
    private int $endAge;

    #[ORM\Column]
    private int $centile1;

    #[ORM\Column]
    private int $centile99;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartAge(): ?int
    {
        return $this->startAge;
    }

    public function setStartAge(int $startAge): self
    {
        $this->startAge = $startAge;

        return $this;
    }

    public function getEndAge(): ?int
    {
        return $this->endAge;
    }

    public function setEndAge(int $endAge): self
    {
        $this->endAge = $endAge;

        return $this;
    }

    public function getCentile1(): ?int
    {
        return $this->centile1;
    }

    public function setCentile1(int $centile1): self
    {
        $this->centile1 = $centile1;

        return $this;
    }

    public function getCentile99(): ?int
    {
        return $this->centile99;
    }

    public function setCentile99(int $centile99): self
    {
        $this->centile99 = $centile99;

        return $this;
    }
}
