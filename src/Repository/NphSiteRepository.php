<?php

namespace App\Repository;

use App\Entity\NphSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NphSite|null find($id, $lockMode = null, $lockVersion = null)
 * @method NphSite|null findOneBy(array $criteria, array $orderBy = null)
 * @method NphSite[]    findAll()
 * @method NphSite[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NphSiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NphSite::class);
    }

    // /**
    //  * @return NphSite[] Returns an array of NphSite objects
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
    public function findOneBySomeField($value): ?NphSite
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
