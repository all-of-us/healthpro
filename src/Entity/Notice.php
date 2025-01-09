<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'notices')]
#[ORM\Entity(repositoryClass: 'App\Repository\NoticeRepository')]
class Notice
{
    public const NPH_TYPE = 'nph';
    public const AOU_TYPE = 'aou';
    public const NPH_ROUTE_PREFIX = 'nph_';

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\GeneratedValue]
    private $id;

    #[ORM\Column(name: 'url', type: 'string', length: 255, nullable: false)]
    private $url;

    #[ORM\Column(name: 'message', type: 'text', length: 65535, nullable: false)]
    private $message;

    #[ORM\Column(name: 'full_page', type: 'boolean', nullable: false)]
    private $fullPage = false;

    #[ORM\Column(name: 'start_ts', type: 'datetime', nullable: true)]
    private $startTs;

    #[ORM\Column(name: 'end_ts', type: 'datetime', nullable: true)]
    private $endTs;

    #[ORM\Column(name: 'status', type: 'boolean', nullable: false)]
    private $status = false;

    #[ORM\Column(name: 'type', type: 'string', length: 50, nullable: true)]
    private $type;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

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

    public function getFullPage(): ?bool
    {
        return $this->fullPage;
    }

    public function setFullPage(bool $fullPage): self
    {
        $this->fullPage = $fullPage;

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
