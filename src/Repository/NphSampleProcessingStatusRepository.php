<?php

namespace App\Repository;

use App\Entity\NphSampleProcessingStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NphSampleProcessingStatus>
 *
 * @method NphSampleProcessingStatus|null find($id, $lockMode = null, $lockVersion = null)
 * @method NphSampleProcessingStatus|null findOneBy(array $criteria, array $orderBy = null)
 * @method NphSampleProcessingStatus[]    findAll()
 * @method NphSampleProcessingStatus[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NphSampleProcessingStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NphSampleProcessingStatus::class);
    }

//    /**
//     * @return NphSampleProcessingStatus[] Returns an array of NphSampleProcessingStatus objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('n')
//            ->andWhere('n.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('n.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?NphSampleProcessingStatus
//    {
//        return $this->createQueryBuilder('n')
//            ->andWhere('n.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
