<?php

namespace App\Repository;

use App\Entity\NphAliquot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NphAliquot|null find($id, $lockMode = null, $lockVersion = null)
 * @method NphAliquot|null findOneBy(array $criteria, array $orderBy = null)
 * @method NphAliquot[]    findAll()
 * @method NphAliquot[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NphAliquotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NphAliquot::class);
    }

    // /**
    //  * @return NphAliquot[] Returns an array of NphAliquot objects
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
    public function findOneBySomeField($value): ?NphAliquot
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
