<?php

namespace App\Entity;

use App\Repository\HeightForAge24MonthsTo6YearsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HeightForAge24MonthsTo6YearsRepository::class)]
class HeightForAge24MonthsTo6Years
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $sex = null;

    #[ORM\Column]
    private ?int $month = null;

    #[ORM\Column]
    private ?float $L = null;

    #[ORM\Column]
    private ?float $M = null;

    #[ORM\Column]
    private ?float $S = null;

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

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function setMonth(int $month): self
    {
        $this->month = $month;

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
