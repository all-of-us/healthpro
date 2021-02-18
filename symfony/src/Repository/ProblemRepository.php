<?php

namespace App\Repository;

use App\Entity\Problem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Problem|null find($id, $lockMode = null, $lockVersion = null)
 * @method Problem|null findOneBy(array $criteria, array $orderBy = null)
 * @method Problem[]    findAll()
 * @method Problem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProblemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Problem::class);
    }

    public function getProblemsWithCommentsCount()
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.problemComments', 'pc')
            ->addSelect('IFNULL(MAX(pc.createdTs), p.updatedTs) AS lastUpdateTs')
            ->addSelect('COUNT(pc.comment) AS commentCount')
            ->groupBy('p.id')
            ->orderBy('IFNULL(MAX(pc.createdTs), p.updatedTs)', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
