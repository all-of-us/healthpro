<?php

namespace App\Repository;

use App\Entity\IncentiveImportRow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method IncentiveImportRow|null find($id, $lockMode = null, $lockVersion = null)
 * @method IncentiveImportRow|null findOneBy(array $criteria, array $orderBy = null)
 * @method IncentiveImportRow[]    findAll()
 * @method IncentiveImportRow[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IncentiveImportRowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IncentiveImportRow::class);
    }

    // /**
    //  * @return IncentiveImportRow[] Returns an array of IncentiveImportRow objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?IncentiveImportRow
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
