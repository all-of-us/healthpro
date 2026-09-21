<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderHistory>
 */
class OrderHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderHistory::class);
    }

    public function getLastOrderHistoryUnlocked(int $orderId): ?OrderHistory
    {
        return $this->createQueryBuilder('oh')
            ->andWhere('oh.order = :orderId')
            ->andWhere('oh.type = :type')
            ->setParameter('orderId', $orderId)
            ->setParameter('type', Order::ORDER_UNLOCK)
            ->orderBy('oh.createdTs', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
