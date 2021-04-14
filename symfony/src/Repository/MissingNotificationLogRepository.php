<?php

namespace App\Repository;

use App\Entity\MissingNotificationLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MissingNotificationLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method MissingNotificationLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method MissingNotificationLog[]    findAll()
 * @method MissingNotificationLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MissingNotificationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MissingNotificationLog::class);
    }

    // /**
    //  * @return MissingNotificationLog[] Returns an array of MissingNotificationLog objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?MissingNotificationLog
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
