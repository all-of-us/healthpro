<?php

namespace App\Repository;

use App\Entity\EvaluationHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method EvaluationHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method EvaluationHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method EvaluationHistory[]    findAll()
 * @method EvaluationHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EvaluationHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EvaluationHistory::class);
    }

    // /**
    //  * @return EvaluationHistory[] Returns an array of EvaluationHistory objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?EvaluationHistory
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
