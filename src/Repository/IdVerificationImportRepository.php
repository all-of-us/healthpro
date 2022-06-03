<?php

namespace App\Repository;

use App\Entity\IdVerificationImport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method IdVerificationImport|null find($id, $lockMode = null, $lockVersion = null)
 * @method IdVerificationImport|null findOneBy(array $criteria, array $orderBy = null)
 * @method IdVerificationImport[]    findAll()
 * @method IdVerificationImport[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IdVerificationImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IdVerificationImport::class);
    }

    // /**
    //  * @return IdVerificationImport[] Returns an array of IdVerificationImport objects
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
    public function findOneBySomeField($value): ?IdVerificationImport
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
