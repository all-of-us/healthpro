<?php

namespace App\Repository;

use App\Entity\NphAdminOrderEditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NphAdminOrderEditLog>
 *
 * @method NphAdminOrderEditLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method NphAdminOrderEditLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method NphAdminOrderEditLog[]    findAll()
 * @method NphAdminOrderEditLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NphAdminOrderEditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NphAdminOrderEditLog::class);
    }

//    /**
//     * @return NphAdminOrderEditLog[] Returns an array of NphAdminOrderEditLog objects
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

//    public function findOneBySomeField($value): ?NphAdminOrderEditLog
//    {
//        return $this->createQueryBuilder('n')
//            ->andWhere('n.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
