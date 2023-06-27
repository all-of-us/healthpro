<?php

namespace App\Entity;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * ProblemComments
 */
#[ORM\Table(name: 'problem_comments')]
#[ORM\Index(name: 'problem_id', columns: ['problem_id'])]
#[ORM\Entity]
class ProblemComment
{
    /**
     * @var int
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    /**
     * @var int
     */
    #[ORM\Column(type: 'integer', nullable: false)]
    private $userId;

    /**
     * @var string
     */
    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    private $site;

    /**
     * @var string|null
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $staffName;

    /**
     * @var string|null
     */
    #[ORM\Column(type: 'text', length: 65535, nullable: true)]
    private $comment;

    /**
     * @var DateTimeInterface
     */
    #[ORM\Column(type: 'datetime', nullable: false, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private $createdTs;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Problem', inversedBy: 'problemComments')]
    #[ORM\JoinColumn(nullable: false)]
    private $problem;

    public function __construct()
    {
        $this->createdTs = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getSite(): ?string
    {
        return $this->site;
    }

    public function setSite(string $site): self
    {
        $this->site = $site;

        return $this;
    }

    public function getStaffName(): ?string
    {
        return $this->staffName;
    }

    public function setStaffName(?string $staffName): self
    {
        $this->staffName = $staffName;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getCreatedTs(): ?DateTimeInterface
    {
        return $this->createdTs;
    }

    public function setCreatedTs(DateTimeInterface $createdTs): self
    {
        $this->createdTs = $createdTs;

        return $this;
    }

    public function getProblem(): ?Problem
    {
        return $this->problem;
    }

    public function setProblem(?Problem $problem): self
    {
        $this->problem = $problem;

        return $this;
    }
}
