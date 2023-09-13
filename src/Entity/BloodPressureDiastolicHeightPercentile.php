<?php

namespace App\Entity;

use App\Repository\BloodPressureDiastolicHeightPercentileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BloodPressureDiastolicHeightPercentileRepository::class)]
class BloodPressureDiastolicHeightPercentile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $sex;

    #[ORM\Column]
    private int $ageYear;

    #[ORM\Column]
    private int $bpCentile;

    #[ORM\Column]
    private int $heightPer5;

    #[ORM\Column]
    private int $heightPer10;

    #[ORM\Column]
    private int $heightPer25;

    #[ORM\Column]
    private int $heightPer50;

    #[ORM\Column]
    private int $heightPer75;

    #[ORM\Column]
    private int $heightPer90;

    #[ORM\Column]
    private int $heightPer95;

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

    public function getAgeYear(): ?int
    {
        return $this->ageYear;
    }

    public function setAgeYear(int $ageYear): self
    {
        $this->ageYear = $ageYear;

        return $this;
    }

    public function getBpCentile(): ?int
    {
        return $this->bpCentile;
    }

    public function setBpCentile(int $bpCentile): self
    {
        $this->bpCentile = $bpCentile;

        return $this;
    }

    public function getHeightPer5(): ?int
    {
        return $this->heightPer5;
    }

    public function setHeightPer5(int $heightPer5): self
    {
        $this->heightPer5 = $heightPer5;

        return $this;
    }

    public function getHeightPer10(): ?int
    {
        return $this->heightPer10;
    }

    public function setHeightPer10(int $heightPer10): self
    {
        $this->heightPer10 = $heightPer10;

        return $this;
    }

    public function getHeightPer25(): ?int
    {
        return $this->heightPer25;
    }

    public function setHeightPer25(int $heightPer25): self
    {
        $this->heightPer25 = $heightPer25;

        return $this;
    }

    public function getHeightPer50(): ?int
    {
        return $this->heightPer50;
    }

    public function setHeightPer50(int $heightPer50): self
    {
        $this->heightPer50 = $heightPer50;

        return $this;
    }

    public function getHeightPer75(): ?int
    {
        return $this->heightPer75;
    }

    public function setHeightPer75(int $heightPer75): self
    {
        $this->heightPer75 = $heightPer75;

        return $this;
    }

    public function getHeightPer90(): ?int
    {
        return $this->heightPer90;
    }

    public function setHeightPer90(int $heightPer90): self
    {
        $this->heightPer90 = $heightPer90;

        return $this;
    }

    public function getHeightPer95(): ?int
    {
        return $this->heightPer95;
    }

    public function setHeightPer95(int $heightPer95): self
    {
        $this->heightPer95 = $heightPer95;

        return $this;
    }
}
