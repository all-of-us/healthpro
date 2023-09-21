<?php

namespace App\Entity;

use App\Repository\WeightForLength23MonthsTo5YearsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WeightForLength23MonthsTo5YearsRepository::class)]
class WeightForLength23MonthsTo5Years
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $sex;

    #[ORM\Column]
    private float $length;

    #[ORM\Column]
    private float $L;

    #[ORM\Column]
    private float $M;

    #[ORM\Column]
    private float $S;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSex(): ?int
    {
        return $this->sex;
    }

    public function setSex(int $sex): self
    {
        $this->sex = $sex;

        return $this;
    }

    public function getLength(): ?float
    {
        return $this->length;
    }

    public function setLength(float $length): self
    {
        $this->length = $length;

        return $this;
    }

    public function getL(): ?float
    {
        return $this->L;
    }

    public function setL(float $L): self
    {
        $this->L = $L;

        return $this;
    }

    public function getM(): ?float
    {
        return $this->M;
    }

    public function setM(float $M): self
    {
        $this->M = $M;

        return $this;
    }

    public function getS(): ?float
    {
        return $this->S;
    }

    public function setS(float $S): self
    {
        $this->S = $S;

        return $this;
    }
}
