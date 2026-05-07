<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\WorkqueueViewRepository')]
class WorkqueueView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'boolean')]
    private bool $defaultView = false;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdTs;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $filters = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $columns = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
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

    public function getDefaultView(): bool
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

    /**
     * @return array<string, mixed>
     */
    public function getFiltersArray(): array
    {
        if (!empty($this->filters)) {
            $filters = json_decode($this->filters, true);
            return is_array($filters) ? $filters : [];
        }
        return [];
    }

    public function getColumnsType(string $type): string
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
