<?php

namespace App\Entity;

use App\Repository\WeightForLength0To23MonthsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WeightForLength0To23MonthsRepository::class)]
class WeightForLength0To23Months
{
    #[ORM\Column]
    private ?int $sex = null;

    #[ORM\Column]
    private ?float $length = null;

    #[ORM\Column]
    private ?float $L = null;

    #[ORM\Column]
    private ?float $M = null;

    #[ORM\Column]
    private ?float $S = null;


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
