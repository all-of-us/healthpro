<?php

namespace App\Repository;

use App\Entity\IdVerificationImportRow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method IdVerificationImportRow|null find($id, $lockMode = null, $lockVersion = null)
 * @method IdVerificationImportRow|null findOneBy(array $criteria, array $orderBy = null)
 * @method IdVerificationImportRow[]    findAll()
 * @method IdVerificationImportRow[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IdVerificationImportRowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IdVerificationImportRow::class);
    }

    // /**
    //  * @return IdVerificationImportRow[] Returns an array of IdVerificationImportRow objects
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
    public function findOneBySomeField($value): ?IdVerificationImportRow
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
