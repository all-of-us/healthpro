<?php

namespace App\Entity;

use App\Repository\ZScoresRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ZScoresRepository::class)]
class ZScores
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?float $Z = null;

    #[ORM\Column]
    private ?float $Z_0 = null;

    #[ORM\Column]
    private ?float $Z_01 = null;

    #[ORM\Column]
    private ?float $Z_02 = null;

    #[ORM\Column]
    private ?float $Z_03 = null;

    #[ORM\Column]
    private ?float $Z_04 = null;

    #[ORM\Column]
    private ?float $Z_05 = null;

    #[ORM\Column]
    private ?float $Z_06 = null;

    #[ORM\Column]
    private ?float $Z_07 = null;

    #[ORM\Column]
    private ?float $Z_08 = null;

    #[ORM\Column]
    private ?float $Z_09 = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getZ(): ?float
    {
        return $this->Z;
    }

    public function setZ(float $Z): self
    {
        $this->Z = $Z;

        return $this;
    }

    public function getZ0(): ?float
    {
        return $this->Z_0;
    }

    public function setZ0(float $Z_0): self
    {
        $this->Z_0 = $Z_0;

        return $this;
    }

    public function getZ01(): ?float
    {
        return $this->Z_01;
    }

    public function setZ01(float $Z_01): self
    {
        $this->Z_01 = $Z_01;

        return $this;
    }

    public function getZ02(): ?float
    {
        return $this->Z_02;
    }

    public function setZ02(float $Z_02): self
    {
        $this->Z_02 = $Z_02;

        return $this;
    }

    public function getZ03(): ?float
    {
        return $this->Z_03;
    }

    public function setZ03(float $Z_03): self
    {
        $this->Z_03 = $Z_03;

        return $this;
    }

    public function getZ04(): ?float
    {
        return $this->Z_04;
    }

    public function setZ04(float $Z_04): self
    {
        $this->Z_04 = $Z_04;

        return $this;
    }

    public function getZ05(): ?float
    {
        return $this->Z_05;
    }

    public function setZ05(float $Z_05): self
    {
        $this->Z_05 = $Z_05;

        return $this;
    }

    public function getZ06(): ?float
    {
        return $this->Z_06;
    }

    public function setZ06(float $Z_06): self
    {
        $this->Z_06 = $Z_06;

        return $this;
    }

    public function getZ07(): ?float
    {
        return $this->Z_07;
    }

    public function setZ07(float $Z_07): self
    {
        $this->Z_07 = $Z_07;

        return $this;
    }

    public function getZ08(): ?float
    {
        return $this->Z_08;
    }

    public function setZ08(float $Z_08): self
    {
        $this->Z_08 = $Z_08;

        return $this;
    }

    public function getZ09(): ?float
    {
        return $this->Z_09;
    }

    public function setZ09(float $Z_09): self
    {
        $this->Z_09 = $Z_09;

        return $this;
    }
}
