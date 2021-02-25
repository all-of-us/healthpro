<?php

namespace App\Repository;

use App\Entity\Order;
use App\Service\ReviewService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Order|null find($id, $lockMode = null, $lockVersion = null)
 * @method Order|null findOneBy(array $criteria, array $orderBy = null)
 * @method Order[]    findAll()
 * @method Order[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

     /**
      * @return Order[] Returns an array of Order objects
      */
    public function getMissingOrders()
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.history', 'oh')
            ->where('o.finalizedTs is not null')
            ->andWhere('o.rdrId is null')
            ->andWhere('oh.type != :type OR oh.type is null')
            ->setParameter('type', 'cancel')
            ->orderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Order[] Returns an array of Order objects
     */
    public function getDuplicateFedexTracking($orderId, $fedexTracking)
    {
        return $this->createQueryBuilder('o')
            ->where('o.fedexTracking = :fedexTracking')
            ->andWhere('o.id != :orderId')
            ->setParameters(['fedexTracking' => $fedexTracking, 'orderId' => $orderId])
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @return array
     */
    public function getSiteUnfinalizedOrders($siteId)
    {
        $ordersQuery = "
            SELECT o.*,
                   oh.order_id AS oh_order_id,
                   oh.user_id AS oh_user_id,
                   oh.site AS oh_site,
                   oh.type AS h_type,
                   oh.created_ts AS oh_created_ts
            FROM orders o
            LEFT JOIN orders_history oh ON o.history_id = oh.id
            WHERE o.site = :site
              AND (o.finalized_ts IS NULL OR o.biobank_finalized = :biobankFinalized)
              AND ((oh.type != :type1 AND oh.type != :type2)
              OR oh.type IS NULL)
            ORDER BY o.created_ts DESC
        ";
        $orders = $this->getEntityManager()->getConnection()->fetchAll($ordersQuery, [
            'site' => $siteId,
            'type1' => Order::ORDER_CANCEL,
            'type2' => Order::ORDER_EDIT,
            'biobankFinalized' => 1
        ]);
        foreach ($orders as $key => $order) {
            foreach (ReviewService::$orderStatus as $field => $status) {
                if ($order[$field]) {
                    $orders[$key]['orderStatus'] = ReviewService::getOrderStatus($order, $status);
                }
            }
        }
        return $orders;
    }

    /**
     * @return array
     */
    public function getSiteUnlockedOrders($siteId)
    {
        $ordersQuery = "
            SELECT o.*,
                   oh.order_id AS oh_order_id,
                   oh.user_id AS oh_user_id,
                   oh.site AS oh_site,
                   oh.type AS h_type,
                   oh.created_ts AS oh_created_ts,
                   'Unlocked' as orderStatus
            FROM orders o
            INNER JOIN orders_history oh ON o.history_id = oh.id
            WHERE o.site = :site
              AND oh.type = :type
            ORDER BY o.created_ts DESC
        ";
        return $this->getEntityManager()->getConnection()->fetchAll($ordersQuery, [
            'site' => $siteId,
            'type' => Order::ORDER_UNLOCK
        ]);
    }

    /**
     * @return array
     */
    public function getSiteRecentModifiedOrders($siteId)
    {
        $ordersQuery = "
            SELECT o.*,
                   oh.order_id AS oh_order_id,
                   oh.user_id AS oh_user_id,
                   oh.site AS oh_site,
                   oh.type AS oh_type,
                   oh.created_ts AS oh_created_ts
            FROM orders o
            INNER JOIN orders_history oh ON o.history_id = oh.id
            WHERE o.site = :site
              AND oh.type != :type1
              AND oh.type != :type2
              AND oh.created_ts >= UTC_TIMESTAMP() - INTERVAL 7 DAY
            ORDER BY oh.created_ts DESC
        ";
        return $this->getEntityManager()->getConnection()->fetchAll($ordersQuery, [
            'site' => $siteId,
            'type1' => Order::ORDER_ACTIVE,
            'type2' => Order::ORDER_RESTORE
        ]);
    }
}
