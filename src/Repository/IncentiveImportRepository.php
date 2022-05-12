<?php

namespace App\Repository;

use App\Entity\IncentiveImport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method IncentiveImport|null find($id, $lockMode = null, $lockVersion = null)
 * @method IncentiveImport|null findOneBy(array $criteria, array $orderBy = null)
 * @method IncentiveImport[]    findAll()
 * @method IncentiveImport[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IncentiveImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IncentiveImport::class);
    }

    // /**
    //  * @return IncentiveImport[] Returns an array of IncentiveImport objects
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
    public function findOneBySomeField($value): ?IncentiveImport
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
