<?php

namespace App\Repository;

use App\Entity\NphOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NphOrder|null find($id, $lockMode = null, $lockVersion = null)
 * @method NphOrder|null findOneBy(array $criteria, array $orderBy = null)
 * @method NphOrder[]    findAll()
 * @method NphOrder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NphOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NphOrder::class);
    }

    public function getOrdersByVisitType($participantId, $visitType): array
    {
        return $this->createQueryBuilder('no')
            ->where('no.participantId = :participantId')
            ->andWhere('no.visitType = :visitType')
            ->setParameter('participantId', $participantId)
            ->setParameter('visitType', $visitType)
            ->orderBy('no.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getOrdersBySampleGroup($participantId, $sampleGroup) {
        return $this->createQueryBuilder('no')
            ->leftJoin('no.nphSamples', 'ns')
            ->where('no.participantId = :participantId')
            ->andWhere('ns.sampleGroup = :sampleGroup')
            ->setParameter('participantId', $participantId)
            ->setParameter('sampleGroup', $sampleGroup)
            ->orderBy('no.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
