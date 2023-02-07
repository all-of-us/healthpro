<?php

namespace App\Repository;

use App\Entity\NphOrder;
use DateTime;
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

    public function getOrdersBySampleGroup(string $participantId, string $sampleGroup): array
    {
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

    public function getRecentOrdersBySite(string $siteId): array
    {
        return $this->createQueryBuilder('no')
            ->where('no.createdTs >= :createdTs')
            ->andWhere('no.site = :site')
            ->setParameters(['site' => $siteId, 'createdTs' => (new DateTime('-1 day'))->format('Y-m-d H:i:s')])
            ->orderBy('no.createdTs', 'DESC')
            ->addOrderBy('no.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getOrdersByDateRange(string $siteId, DateTime $startDate, DateTime $endDate): array
    {
        return $this->createQueryBuilder('no')
            ->select('no.participantId, no.timepoint, group_concat(u.email) as email, no.id as hpoOrderId,
             no.orderId, group_concat(IFNULL(ns.sampleCode, \'\')) as sampleCode, group_concat(IFNULL(ns.sampleId, \'\')) as sampleId,
              group_concat(IFNULL(no.createdTs, \'\')) as createdTs, group_concat(IFNULL(ns.collectedTs, \'\')) as collectedTs,
               group_concat(IFNULL(ns.finalizedTs, \'\')) as finalizedTs, count(no.createdTs) as createdCount,
               count(ns.collectedTs) as collectedCount, count(ns.finalizedTs) as finalizedCount')
            ->join('no.nphSamples', 'ns')
            ->join('no.user', 'u')
            ->where('no.createdTs >= :startDate')
            ->andWhere('no.createdTs <= :endDate')
            ->andWhere('no.site = :site')
            ->setParameters(['site' => $siteId, 'startDate' => $startDate, 'endDate' => $endDate])
            ->orderBy('no.participantId', 'DESC')
            ->addOrderBy('no.timepoint', 'DESC')
            ->addOrderBy('no.orderId', 'DESC')
            ->groupBy('no.participantId, no.timepoint, no.orderId')
            ->getQuery()
            ->getResult();
    }

    public function getSampleCollectionStatsByDate(string $siteId, DateTime $startDate, DateTime $endDate): array
    {
        return $this->createQueryBuilder('no')
            ->select('count(no.createdTs) as createdCount, count(ns.collectedTs) as collectedCount, count(ns.finalizedTs) as finalizedCount')
            ->join('no.nphSamples', 'ns')
            ->where('no.createdTs >= :startDate')
            ->andWhere('no.createdTs <= :endDate')
            ->andWhere('no.site = :site')
            ->setParameters(['site' => $siteId, 'startDate' => $startDate, 'endDate' => $endDate])
            ->getQuery()
            ->getResult();
    }

    public function getUnfinalizedSampleCollectionStats(string $siteId): array
    {
        return $this->createQueryBuilder('no')
            ->select('count(no.createdTs) as createdCount, count(ns.collectedTs) as collectedCount, count(ns.finalizedTs) as finalizedCount')
            ->join('no.nphSamples', 'ns')
            ->where('no.site = :site')
            ->andWhere('ns.finalizedTs IS NULL')
            ->setParameter('site', $siteId)
            ->getQuery()
            ->getResult();
    }

    public function getUnfinalizedSamples(string $site): array
    {
        return $this->createQueryBuilder('no')
            ->select('no.id as hpoOrderId, no.orderId, no.participantId, no.timepoint, no.visitType,
             no.createdTs, ns.sampleId, ns.sampleCode, ns.sampleGroup, ns.collectedTs, ns.finalizedTs, ns.modifyType')
            ->join('no.nphSamples', 'ns')
            ->where('ns.finalizedTs IS NULL')
            ->andWhere('no.site = :site')
            ->setParameter('site', $site)
            ->orderBy('no.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getRecentlyModifiedSamples(string $site, DateTime $modifiedTs): array
    {
        return $this->createQueryBuilder('no')
            ->select('no.id as hpoOrderId, no.orderId, no.participantId, no.timepoint, no.visitType,
             no.createdTs, ns.sampleId, ns.sampleCode, ns.sampleGroup, ns.collectedTs, ns.finalizedTs, ns.modifyType')
            ->join('no.nphSamples', 'ns')
            ->where('ns.modifiedTs >= :modifiedTs')
            ->andWhere('no.site = :site')
            ->setParameters(['site' => $site, 'modifiedTs' => $modifiedTs])
            ->orderBy('no.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
