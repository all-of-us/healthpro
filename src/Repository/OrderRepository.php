<?php

namespace App\Repository;

use App\Entity\MissingNotificationLog;
use App\Entity\Order;
use App\Entity\Site;
use App\Service\ReviewService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
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
            ->andWhere('o.mayoId is not null')
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
    public function getDuplicateFedexTracking($fedexTracking, $orderId)
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
        $ordersQuery = '
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
        ';
        $orders = $this->getEntityManager()->getConnection()->fetchAllAssociative($ordersQuery, [
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
        return $this->getEntityManager()->getConnection()->fetchAllAssociative($ordersQuery, [
            'site' => $siteId,
            'type' => Order::ORDER_UNLOCK
        ]);
    }

    /**
     * @return array
     */
    public function getSiteRecentModifiedOrders($siteId)
    {
        $ordersQuery = '
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
        ';
        return $this->getEntityManager()->getConnection()->fetchAllAssociative($ordersQuery, [
            'site' => $siteId,
            'type1' => Order::ORDER_ACTIVE,
            'type2' => Order::ORDER_RESTORE
        ]);
    }

    public function getUnfinalizedOrders(): array
    {
        $ordersQuery = '
            SELECT o.*,
                   oh.order_id AS oh_order_id,
                   oh.user_id AS oh_user_id,
                   oh.site AS oh_site,
                   oh.type AS h_type,
                   oh.created_ts AS oh_created_ts,
                   s.name as created_site_name,
                   sc.name as collected_site_name,
                   sp.name as processed_site_name,
                   sf.name as finalized_site_name
            FROM orders o
            LEFT JOIN orders_history oh ON o.history_id = oh.id
            LEFT JOIN sites s ON s.site_id = o.site AND s.deleted = :deleted
            LEFT JOIN sites sc ON sc.site_id = o.collected_site AND sc.deleted = :deleted
            LEFT JOIN sites sp ON sp.site_id = o.processed_site AND sp.deleted = :deleted
            LEFT JOIN sites sf ON sf.site_id = o.finalized_site AND sf.deleted = :deleted
            WHERE (o.finalized_ts IS NULL OR o.biobank_finalized = :biobankFinalized)
              AND ((oh.type != :type1 AND oh.type != :type2)
              OR oh.type IS NULL)
            ORDER BY o.created_ts DESC
        ';
        $orders = $this->getEntityManager()->getConnection()->fetchAllAssociative($ordersQuery, [
            'type1' => Order::ORDER_CANCEL,
            'type2' => Order::ORDER_EDIT,
            'biobankFinalized' => 1,
            'deleted' => 0
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

    public function getUnlockedOrders(): array
    {
        $ordersQuery = '
            SELECT o.*,
                   oh.order_id AS oh_order_id,
                   oh.user_id AS oh_user_id,
                   oh.site AS oh_site,
                   oh.type AS oh_type,
                   oh.created_ts AS oh_created_ts,
                   s.name as created_site_name,
                   sc.name as collected_site_name,
                   sp.name as processed_site_name,
                   sf.name as finalized_site_name
            FROM orders o
            INNER JOIN orders_history oh ON o.history_id = oh.id
            LEFT JOIN sites s ON s.site_id = o.site AND s.deleted = :deleted
            LEFT JOIN sites sc ON sc.site_id = o.collected_site AND sc.deleted = :deleted
            LEFT JOIN sites sp ON sp.site_id = o.processed_site AND sp.deleted = :deleted
            LEFT JOIN sites sf ON sf.site_id = o.finalized_site AND sf.deleted = :deleted
            WHERE oh.type = :type
            ORDER BY o.created_ts DESC
        ';
        return $this->getEntityManager()->getConnection()->fetchAllAssociative($ordersQuery, [
            'type' => Order::ORDER_UNLOCK,
            'deleted' => 0
        ]);
    }

    public function getRecentModifiedOrders(): array
    {
        $ordersQuery = '
            SELECT o.*,
                   oh.order_id AS oh_order_id,
                   oh.user_id AS oh_user_id,
                   oh.site AS oh_site,
                   oh.type AS oh_type,
                   oh.created_ts AS oh_created_ts,
                   oh.created_timezone_id AS oh_created_timezone_id,
                   s.name as created_site_name,
                   sc.name as collected_site_name,
                   sp.name as processed_site_name,
                   sf.name as finalized_site_name
            FROM orders o
            INNER JOIN orders_history oh ON o.history_id = oh.id
            LEFT JOIN sites s ON s.site_id = o.site AND s.deleted = :deleted
            LEFT JOIN sites sc ON sc.site_id = o.collected_site AND sc.deleted = :deleted
            LEFT JOIN sites sp ON sp.site_id = o.processed_site AND sp.deleted = :deleted
            LEFT JOIN sites sf ON sf.site_id = o.finalized_site AND sf.deleted = :deleted
            WHERE oh.type != :type1
              AND oh.type != :type2
              AND oh.created_ts >= UTC_TIMESTAMP() - INTERVAL 7 DAY
            ORDER BY oh.created_ts DESC
        ';
        return $this->getEntityManager()->getConnection()->fetchAllAssociative($ordersQuery, [
            'type1' => Order::ORDER_ACTIVE,
            'type2' => Order::ORDER_RESTORE,
            'deleted' => 0
        ]);
    }


    public function getUnloggedMissingOrders(): array
    {
        $ordersQuery = 'SELECT id FROM orders WHERE id NOT IN (SELECT record_id FROM missing_notifications_log WHERE type = :type) AND finalized_ts IS NOT NULL AND mayo_id IS NOT NULL AND rdr_id IS NULL';
        return $this->getEntityManager()->getConnection()->fetchAllAssociative($ordersQuery, [
            'type' => MissingNotificationLog::ORDER_TYPE
        ]);
    }

    public function getSiteRecentOrders($site): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.site = :site')
            ->andWhere('o.createdTs >= :createdTs')
            ->setParameters(['site' => $site, 'createdTs' => (new \DateTime('-1 day'))->format('Y-m-d H:i:s')])
            ->orderBy('o.createdTs', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function getBackfillOrders($limit)
    {
        return $this->createQueryBuilder('o')
            ->where('o.processedTs < o.collectedTs')
            ->andWhere('o.processedSamplesTs is not null')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function getNightlyReportOrders(): array
    {
        return $this->createQueryBuilder('o')
            ->select('o.biobankId', 'o.orderId', 'o.rdrId', 'o.collectedTs', 'o.finalizedTs', 's.mayolinkAccount')
            ->leftJoin(Site::class, 's', Join::WITH, 'o.finalizedSite = s.siteId')
            ->where('o.rdrId is not null')
            ->andWhere('o.finalizedTs >= :finalizedTs')
            ->setParameter('finalizedTs', (new \DateTime('-1 day'))->format('Y-m-d H:i:s'))
            ->orderBy('o.finalizedTs', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
