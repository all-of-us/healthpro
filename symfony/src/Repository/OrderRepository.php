<?php

namespace App\Repository;

use App\Entity\Order;
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
}
