<?php

namespace App\Repository;

use App\Entity\NphSample;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NphSample|null find($id, $lockMode = null, $lockVersion = null)
 * @method NphSample|null findOneBy(array $criteria, array $orderBy = null)
 * @method NphSample[]    findAll()
 * @method NphSample[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NphSampleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NphSample::class);
    }

    // /**
    //  * @return NphSample[] Returns an array of NphSample objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('n.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?NphSample
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
