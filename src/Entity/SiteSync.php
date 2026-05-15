<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\SiteSyncRepository')]
class SiteSync
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: 'App\Entity\Site', inversedBy: 'siteSync', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private Site $site;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $adminEmailsAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): ?Site
    {
        return $this->site;
    }

    public function setSite(Site $site): self
    {
        $this->site = $site;

        return $this;
    }

    public function getAdminEmailsAt(): ?\DateTimeInterface
    {
        return $this->adminEmailsAt;
    }

    public function setAdminEmailsAt(\DateTimeInterface $adminEmailsAt): self
    {
        $this->adminEmailsAt = $adminEmailsAt;

        return $this;
    }
}
