<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\WorkqueueViewRepository")
 */
class WorkqueueView
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="boolean")
     */
    private $defaultView;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdTs;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $filters;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $columns;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDefaultView(): ?bool
    {
        return $this->defaultView;
    }

    public function setDefaultView(bool $defaultView): self
    {
        $this->defaultView = $defaultView;

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

    public function getFilters(): ?string
    {
        return $this->filters;
    }

    public function setFilters(?string $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    public function getColumns(): ?string
    {
        return $this->columns;
    }

    public function setColumns(?string $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    public function getFiltersArray(): array
    {
        if (!empty($this->filters)) {
            return json_decode($this->filters, true);
        }
        return [];
    }

    public function getColumnsType($type): string
    {
        if ($type === 'custom') {
            $columns = 'workQueueViewColumns';
        } elseif ($type === 'consent') {
            $columns = 'workQueueConsentColumns';
        } else {
            $columns = 'workQueueColumns';
        }
        return $columns;
    }
}
