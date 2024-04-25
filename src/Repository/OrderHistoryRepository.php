<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method OrderHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrderHistory[]    findAll()
 * @method OrderHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderHistory::class);
    }

    public function getLastOrderHistoryUnlocked($orderId)
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
