<?php

namespace App\Repository;

use App\Entity\PatientStatusHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PatientStatusHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method PatientStatusHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method PatientStatusHistory[]    findAll()
 * @method PatientStatusHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PatientStatusHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PatientStatusHistory::class);
    }

    // /**
    //  * @return PatientStatusHistory[] Returns an array of PatientStatusHistory objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?PatientStatusHistory
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
