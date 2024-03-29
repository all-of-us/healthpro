<?php

namespace App\Repository;

use App\Entity\FeatureNotificationUserMap;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method FeatureNotificationUserMap|null find($id, $lockMode = null, $lockVersion = null)
 * @method FeatureNotificationUserMap|null findOneBy(array $criteria, array $orderBy = null)
 * @method FeatureNotificationUserMap[]    findAll()
 * @method FeatureNotificationUserMap[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FeatureNotificationUserMapRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeatureNotificationUserMap::class);
    }

    /**
     * @return FeatureNotificationUserMap[] Returns an array of FeatureNotificationUserMap objects
     */
    public function getUserNotificationIds($user)
    {
        $userNotificationIds = $this->createQueryBuilder('fum')
            ->select('identity(fum.featureNotification)')
            ->where('fum.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
        $ids = [];
        foreach ($userNotificationIds as $userNotificationId) {
            $ids[] = $userNotificationId[1];
        }
        return $ids;
    }
}
