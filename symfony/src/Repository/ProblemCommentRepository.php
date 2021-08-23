<?php

namespace App\Repository;

use App\Entity\ProblemComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ProblemComment|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProblemComment|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProblemComment[]    findAll()
 * @method ProblemComment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProblemCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProblemComment::class);
    }

    public function findByProblemId($problemId)
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
