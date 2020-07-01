<?php

namespace App\Repository;

use App\Entity\PatientStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PatientStatus|null find($id, $lockMode = null, $lockVersion = null)
 * @method PatientStatus|null findOneBy(array $criteria, array $orderBy = null)
 * @method PatientStatus[]    findAll()
 * @method PatientStatus[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PatientStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PatientStatus::class);
    }

    // /**
    //  * @return PatientStatus[] Returns an array of PatientStatus objects
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
    public function findOneBySomeField($value): ?PatientStatus
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
