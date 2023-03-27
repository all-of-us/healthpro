<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FeatureNotificationRepository")
 * @ORM\Table(name="feature_notifications")
 */
class FeatureNotification
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $title;

    /**
     * @ORM\Column(type="text")
     */
    private $message;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $url;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $startTs;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $endTs;

    /**
     * @ORM\Column(type="boolean")
     */
    private $status = false;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdTs;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getStartTs(): ?\DateTimeInterface
    {
        return $this->startTs;
    }

    public function setStartTs(?\DateTimeInterface $startTs): self
    {
        $this->startTs = $startTs;

        return $this;
    }

    public function getEndTs(): ?\DateTimeInterface
    {
        return $this->endTs;
    }

    public function setEndTs(?\DateTimeInterface $endTs): self
    {
        $this->endTs = $endTs;

        return $this;
    }

    public function getStatus(): ?bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedTs(): ?\DateTimeInterface
    {
        return $this->createdTs;
    }

    public function setCreatedTs(\DateTimeInterface $createdTs): self
    {
        $this->createdTs = $createdTs;

        return $this;
    }
}
