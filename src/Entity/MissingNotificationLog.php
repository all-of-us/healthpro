<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'missing_notifications_log')]
#[ORM\Entity(repositoryClass: 'App\Repository\MissingNotificationLogRepository')]
class MissingNotificationLog
{
    public const MEASUREMENT_TYPE = 'measurement';
    public const ORDER_TYPE = 'order';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'integer')]
    private $recordId;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $type;

    #[ORM\Column(type: 'datetime')]
    private $insertTs;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecordId(): ?int
    {
        return $this->recordId;
    }

    public function setRecordId(int $recordId): self
    {
        $this->recordId = $recordId;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getInsertTs(): ?\DateTimeInterface
    {
        return $this->insertTs;
    }

    public function setInsertTs(\DateTimeInterface $insertTs): self
    {
        $this->insertTs = $insertTs;

        return $this;
    }
}
