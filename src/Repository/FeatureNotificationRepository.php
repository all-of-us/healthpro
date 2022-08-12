<?php

namespace App\Repository;

use App\Entity\FeatureNotification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method FeatureNotification|null find($id, $lockMode = null, $lockVersion = null)
 * @method FeatureNotification|null findOneBy(array $criteria, array $orderBy = null)
 * @method FeatureNotification[]    findAll()
 * @method FeatureNotification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FeatureNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeatureNotification::class);
    }

    /**
     * @return FeatureNotification[] Returns an array of currently active FeatureNotification objects
     */
    public function getActiveNotifications()
    {
        return $this->createQueryBuilder('f')
            ->where('f.status = true')
            ->andWhere('f.startTs is null OR f.startTs <= :now')
            ->andWhere('f.endTs is null OR f.endTs >= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('f.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
