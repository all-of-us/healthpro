<?php

namespace App\Repository;

use App\Entity\ProblemComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProblemComment>
 */
class ProblemCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProblemComment::class);
    }

    /**
     * @return array<int, ProblemComment>
     */
    public function findByProblemId(int $problemId): array
    {
        return $this->createQueryBuilder('pc')
            ->where('pc.problem = :problemId')
            ->setParameter('problemId', $problemId)
            ->orderBy('pc.createdTs', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
