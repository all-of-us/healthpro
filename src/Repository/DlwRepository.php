<?php

namespace App\Repository;

use App\Entity\Dlw;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Dlw|null find($id, $lockMode = null, $lockVersion = null)
 * @method Dlw|null findOneBy(array $criteria, array $orderBy = null)
 * @method Dlw[]    findAll()
 * @method Dlw[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DlwRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dlw::class);
    }

    // /**
    //  * @return Dlw[] Returns an array of Dlw objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Dlw
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
