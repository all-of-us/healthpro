<?php

namespace App\Repository;

use App\Entity\FeatureNotificationUserMap;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FeatureNotificationUserMap>
 */
class FeatureNotificationUserMapRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeatureNotificationUserMap::class);
    }

    /**
     * @return list<int>
     */
    public function getUserNotificationIds(User $user): array
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
