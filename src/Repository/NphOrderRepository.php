<?php

namespace App\Repository;

use App\Entity\NphFieldSort;
use App\Entity\NphOrder;
use App\Entity\NphSample;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
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

    public function getOrdersByVisitType($participantId, $visitType, $module): array
    {
        return $this->createQueryBuilder('no')
            ->where('no.participantId = :participantId')
            ->andWhere('no.visitType = :visitType')
            ->andWhere('no.module = :module')
            ->setParameter('participantId', $participantId)
            ->setParameter('visitType', $visitType)
            ->setParameter('module', $module)
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

    public function getOrdersByDateRange(DateTime $startDate, DateTime $endDate, string $siteId = null): array
    {
        $queryBuilder = $this->createQueryBuilder('no')
            ->select('no.participantId, no.biobankId, no.site, no.timepoint, no.module, no.visitType, group_concat(u.email) as email, no.id as hpoOrderId,
             no.orderId, group_concat(IFNULL(ns.sampleCode, \'\')) as sampleCode, group_concat(IFNULL(ns.sampleId, \'\')) as sampleId,
              group_concat(IFNULL(no.createdTs, \'\')) as createdTs, group_concat(IFNULL(ns.collectedTs, \'\')) as collectedTs,
               group_concat(IFNULL(ns.finalizedTs, \'\')) as finalizedTs, count(no.createdTs) as createdCount,
               count(ns.collectedTs) as collectedCount, count(ns.finalizedTs) as finalizedCount, group_concat(nfs.sortOrder) as sortOrder')
            ->join('no.nphSamples', 'ns')
            ->join('no.user', 'u')
            ->leftJoin(NphFieldSort::class, 'nfs', Join::WITH, 'nfs.fieldValue = no.timepoint')
            ->where('ns.modifiedTs >= :startDate or no.createdTs >= :startDate or ns.finalizedTs >= :startDate or ns.collectedTs >= :startDate')
            ->andWhere('ns.modifiedTs <= :endDate or no.createdTs <= :endDate or ns.finalizedTs <= :endDate or ns.collectedTs <= :endDate');
        if ($siteId) {
            $queryBuilder->andWhere('no.site = :site');
        }
        $queryBuilder
            ->setParameters($this->getDateRangeParams($startDate, $endDate, $siteId))
            ->orderBy('no.participantId', 'DESC')
            ->addorderBy('no.module', 'ASC')
            ->addorderBy('no.visitType', 'DESC')
            ->addOrderBy('nfs.sortOrder', 'asc')
            ->addOrderBy('no.orderId', 'DESC')
            ->groupBy('no.participantId, no.module, no.timepoint, no.orderId, nfs.sortOrder');
        return $queryBuilder->getQuery()->getResult();
    }

    public function getSampleCollectionStatsByDate(DateTime $startDate, DateTime $endDate, string $siteId = null): array
    {
        $queryBuilder = $this->createQueryBuilder('no')
            ->select('count(no.createdTs) as createdCount, count(ns.collectedTs) as collectedCount, count(ns.finalizedTs) as finalizedCount')
            ->join('no.nphSamples', 'ns')
            ->where('ns.modifiedTs >= :startDate or no.createdTs >= :startDate or ns.finalizedTs >= :startDate or ns.collectedTs >= :startDate')
            ->andWhere('ns.modifiedTs <= :endDate or no.createdTs <= :endDate or ns.finalizedTs <= :endDate or ns.collectedTs <= :endDate');
        if ($siteId) {
            $queryBuilder->andWhere('no.site = :site');
        }
        $queryBuilder
            ->setParameters($this->getDateRangeParams($startDate, $endDate, $siteId));
        return $queryBuilder->getQuery()->getResult();
    }

    public function getUnfinalizedSampleCollectionStats(string $siteId): array
    {
        return $this->createQueryBuilder('no')
            ->select('count(no.createdTs) as createdCount, count(ns.collectedTs) as collectedCount, count(ns.finalizedTs) as finalizedCount')
            ->join('no.nphSamples', 'ns')
            ->where('no.site = :site')
            ->andWhere('ns.finalizedTs IS NULL')
            ->andWhere('ns.modifyType != :modifyType OR ns.modifyType IS NULL')
            ->setParameters(['site' => $siteId, 'modifyType' => NphSample::CANCEL])
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
            ->andWhere('ns.modifyType != :modifyType OR ns.modifyType IS NULL')
            ->setParameters(['site' => $site, 'modifyType' => NphSample::CANCEL])
            ->orderBy('no.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getRecentlyModifiedSamples(string $site, DateTime $modifiedTs): array
    {
        return $this->createQueryBuilder('no')
            ->select('no.id as hpoOrderId, no.orderId, no.participantId, no.timepoint, no.visitType,
             no.createdTs, ns.sampleId, ns.sampleCode, ns.sampleGroup, ns.collectedTs, ns.finalizedTs, ns.modifyType, ns.modifiedTs')
            ->join('no.nphSamples', 'ns')
            ->where('ns.modifiedTs >= :modifiedTs')
            ->andWhere('no.site = :site')
            ->setParameters(['site' => $site, 'modifiedTs' => $modifiedTs])
            ->orderBy('no.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getOrdersByParticipantId(string $participantId): array
    {
        return $this->createQueryBuilder('no')
            ->where('no.participantId = :participantId')
            ->setParameter('participantId', $participantId)
            ->orderBy('no.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    private function getDateRangeParams(DateTime $startDate, DateTime $endDate, ?string $siteId): array
    {
        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate
        ];
        if ($siteId) {
            $params['site'] = $siteId;
        }
        return $params;
    }
}
