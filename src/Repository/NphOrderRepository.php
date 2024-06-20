<?php

namespace App\Repository;

use App\Entity\NphFieldSort;
use App\Entity\NphOrder;
use App\Entity\NphSample;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
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

    public function getOrdersByVisitType($participantId, $visitPeriod, $module): array
    {
        return $this->createQueryBuilder('no')
            ->where('no.participantId = :participantId')
            ->andWhere('no.visitPeriod = :visitPeriod')
            ->andWhere('no.module = :module')
            ->setParameter('participantId', $participantId)
            ->setParameter('visitPeriod', $visitPeriod)
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

    public function getOrdersByDateRange(string $siteId, DateTime $startDate, DateTime $endDate): array
    {
        $queryBuilder = $this->createQueryBuilder('no')
            ->select('no.participantId, no.timepoint, no.module, no.visitPeriod, group_concat(u.email) as email, no.id as hpoOrderId,
             no.orderId, group_concat(IFNULL(ns.sampleCode, \'\')) as sampleCode, group_concat(IFNULL(ns.sampleId, \'\')) as sampleId,
              group_concat(IFNULL(no.createdTs, \'\')) as createdTs, group_concat(IFNULL(ns.collectedTs, \'\')) as collectedTs,
               group_concat(IFNULL(ns.finalizedTs, \'\')) as finalizedTs, count(no.createdTs) as createdCount,
               count(ns.collectedTs) as collectedCount, count(ns.finalizedTs) as finalizedCount, group_concat(nfs.sortOrder) as sortOrder')
            ->join('no.nphSamples', 'ns')
            ->join('no.user', 'u')
            ->leftJoin(NphFieldSort::class, 'nfs', Join::WITH, 'nfs.fieldValue = no.timepoint')
            ->where('ns.modifiedTs >= :startDate or no.createdTs >= :startDate or ns.finalizedTs >= :startDate or ns.collectedTs >= :startDate')
            ->andWhere('ns.modifiedTs <= :endDate or no.createdTs <= :endDate or ns.finalizedTs <= :endDate or ns.collectedTs <= :endDate')
            ->andWhere('no.site = :site')
            ->setParameters(['site' => $siteId, 'startDate' => $startDate, 'endDate' => $endDate])
            ->orderBy('no.participantId', 'DESC')
            ->addorderBy('no.module', 'ASC')
            ->addorderBy('no.visitPeriod', 'DESC')
            ->addOrderBy('nfs.sortOrder', 'asc')
            ->addOrderBy('no.orderId', 'DESC')
            ->groupBy('no.participantId, no.module, no.timepoint, no.orderId, nfs.sortOrder')
            ->getQuery();
        return $queryBuilder->getResult();
    }

    public function getSampleCollectionStatsByDate(string $siteId, DateTime $startDate, DateTime $endDate): array
    {
        return $this->createQueryBuilder('no')
            ->select('count(no.createdTs) as createdCount, count(ns.collectedTs) as collectedCount, count(ns.finalizedTs) as finalizedCount')
            ->join('no.nphSamples', 'ns')
            ->where('ns.modifiedTs >= :startDate or no.createdTs >= :startDate or ns.finalizedTs >= :startDate or ns.collectedTs >= :startDate')
            ->andWhere('ns.modifiedTs <= :endDate or no.createdTs <= :endDate or ns.finalizedTs <= :endDate or ns.collectedTs <= :endDate')
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
            ->andWhere('ns.modifyType != :modifyType OR ns.modifyType IS NULL')
            ->setParameters(['site' => $siteId, 'modifyType' => NphSample::CANCEL])
            ->getQuery()
            ->getResult();
    }

    public function getUnfinalizedSamples(string $site): array
    {
        return $this->createQueryBuilder('no')
            ->select('no.id as hpoOrderId, no.orderId, no.participantId, no.timepoint, no.visitPeriod,
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
            ->select('no.id as hpoOrderId, no.orderId, no.participantId, no.timepoint, no.visitPeriod,
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

    public function getTodaysBiobankOrders(string $timezone): array
    {
        $startDate = new \DateTime('today', new \DateTimeZone($timezone));
        $endDate = new \DateTime('tomorrow', new \DateTimeZone($timezone));
        $queryBuilder = $this->createQueryBuilder('no')
            ->select('no.participantId, no.biobankId, no.site, no.timepoint, no.module, no.visitPeriod, u.email as email, no.id as hpoOrderId,
             no.orderId, no.DowntimeGenerated, ns.sampleCode as sampleCode, ns.sampleId as sampleId, no.createdTs, no.createdTimezoneId, ns.collectedTs, ns.collectedTimezoneId,
             ns.finalizedTs, ns.finalizedTimezoneId, ns.biobankFinalized, ns.modifyType')
            ->join('no.nphSamples', 'ns')
            ->join('no.user', 'u')
            ->where('ns.modifiedTs >= :startDate or no.createdTs >= :startDate or ns.finalizedTs >= :startDate or ns.collectedTs >= :startDate')
            ->andWhere('ns.modifiedTs <= :endDate or no.createdTs <= :endDate or ns.finalizedTs <= :endDate or ns.collectedTs <= :endDate')
            ->setParameters(['startDate' => $startDate, 'endDate' => $endDate])
            ->addOrderBy('no.orderId', 'DESC');
        return $queryBuilder->getQuery()->getResult();
    }

    public function getUnfinalizedBiobankSamples(): array
    {
        $queryBuilder = $this->createQueryBuilder('no')
            ->select('no.participantId, no.biobankId, no.site, no.timepoint, no.module, no.visitPeriod, u.email as email, no.id as hpoOrderId,
             no.orderId, no.DowntimeGenerated, ns.sampleCode as sampleCode, ns.sampleId as sampleId, no.createdTs, no.createdTimezoneId, ns.collectedTs, ns.collectedTimezoneId,
             ns.finalizedTs, ns.finalizedTimezoneId, ns.biobankFinalized, ns.modifyType')
            ->join('no.nphSamples', 'ns')
            ->join('no.user', 'u')
            ->where('ns.finalizedTs is NULL')
            ->addOrderBy('no.orderId', 'DESC');
        return $queryBuilder->getQuery()->getResult();
    }

    public function getUnlockedBiobankSamples(): array
    {
        $queryBuilder = $this->createQueryBuilder('no')
            ->select('no.participantId, no.biobankId, no.site, no.timepoint, no.module, no.visitPeriod, u.email as email, no.id as hpoOrderId,
             no.orderId, no.DowntimeGenerated, ns.sampleCode as sampleCode, ns.sampleId as sampleId, no.createdTs, no.createdTimezoneId, ns.collectedTs, ns.collectedTimezoneId,
             ns.finalizedTs, ns.finalizedTimezoneId, ns.biobankFinalized, ns.modifyType')
            ->join('no.nphSamples', 'ns')
            ->join('no.user', 'u')
            ->where('ns.modifyType = :modifyType')
            ->setParameter('modifyType', NphSample::UNLOCK)
            ->addOrderBy('no.orderId', 'DESC');
        return $queryBuilder->getQuery()->getResult();
    }

    public function getRecentlyModifiedBiobankSamples(string $timezone): array
    {
        $endDate = new \DateTime('-7 days', new \DateTimeZone($timezone));
        $queryBuilder = $this->createQueryBuilder('no')
            ->select('no.participantId, no.biobankId, no.site, no.timepoint, no.module, no.visitPeriod, u.email as email, no.id as hpoOrderId,
             no.orderId, no.DowntimeGenerated, ns.sampleCode as sampleCode, ns.sampleId as sampleId, no.createdTs, no.createdTimezoneId, ns.collectedTs, ns.collectedTimezoneId,
             ns.finalizedTs, ns.finalizedTimezoneId, ns.modifiedTs, ns.modifiedTimezoneId, ns.biobankFinalized, ns.modifyType')
            ->join('no.nphSamples', 'ns')
            ->join('no.user', 'u')
            ->where('ns.modifyType is not NULL')
            ->andWhere('ns.modifiedTs >= :endDate')
            ->setParameter('endDate', $endDate)
            ->addOrderBy('no.orderId', 'DESC');
        return $queryBuilder->getQuery()->getResult();
    }

    public function getDownTimeGeneratedOrdersByModuleAndVisit(string $ParticipantId, string $Module, String $Visit): array
    {
        $queryBuild = $this->createQueryBuilder('no')
            ->where('no.participantId = :participantId')
            ->join('no.nphSamples', 'ns')
            ->andWhere('no.module = :module')
            ->andWhere('no.visitPeriod = :visitPeriod')
            ->andWhere('no.DowntimeGenerated = 1')
            ->andWhere('ns.modifyType != :modifyType OR ns.modifyType IS NULL')
            ->setParameter('participantId', $ParticipantId)
            ->setParameter('module', $Module)
            ->setParameter('modifyType', NphSample::CANCEL)
            ->setParameter('visitPeriod', $Visit);
        return $queryBuild->getQuery()->getResult();
    }

    public function getDowntimeOrders(?DateTime $startDate = null, ?DateTime $endDate = null): array
    {
        $queryBuilder = $this->createQueryBuilder('no')
            ->select('no.participantId, no.biobankId, no.site, no.timepoint, no.module, no.visitPeriod, u.email as email, no.DowntimeGenerated, no.downtimeGeneratedTs, no.id as hpoOrderId,
             no.orderId, ns.sampleCode as sampleCode, ns.sampleId as sampleId, no.createdTs, no.createdTimezoneId, ns.collectedTs, ns.collectedTimezoneId,
             ns.finalizedTs, ns.finalizedTimezoneId, ns.biobankFinalized, ns.modifyType')
            ->join('no.nphSamples', 'ns')
            ->join('no.user', 'u')
            ->where('no.DowntimeGenerated = 1');
        if ($startDate && $endDate) {
            $queryBuilder
                ->andWhere('no.downtimeGeneratedTs >= :startDate')
                ->andWhere('no.downtimeGeneratedTs <= :endDate')
                ->setParameters(['startDate' => $startDate, 'endDate' => $endDate]);
        }
        $queryBuilder
            ->addOrderBy('no.downtimeGeneratedTs', 'DESC');
        return $queryBuilder->getQuery()->getResult();
    }

    public function getOrderSamplesByModule(string $participantId): array
    {
        return $this->createQueryBuilder('no')
            ->select('no.module, no.visitPeriod, ns.finalizedTs, ns.modifyType')
            ->leftJoin('no.nphSamples', 'ns')
            ->where('no.participantId = :participantId')
            ->setParameter('participantId', $participantId)
            ->getQuery()
            ->getResult(Query::HYDRATE_ARRAY);
    }

    public function getParticipantNotInCronSampleProcessingStatusLog(string $backfillTs): array
    {
        return $this->createQueryBuilder('no')
            ->leftJoin('App\Entity\CronNphSampleProcessingStatusLog', 'cnspsl', 'WITH', 'no.participantId = cnspsl.participantId AND no.module = cnspsl.module AND no.visitPeriod LIKE CONCAT(cnspsl.period, \'%\')')
            ->where('no.createdTs < :backfillTs')
            ->andWhere('cnspsl.participantId IS NULL')
            ->setParameter('backfillTs', $backfillTs)
            ->orderBy('no.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();
    }

    public function getOrdersByParticipantAndPeriod(string $participantId, string $module, string $visitPeriod): array
    {
        return $this->createQueryBuilder('no')
            ->where('no.participantId = :participantId')
            ->andWhere('no.module = :module')
            ->andWhere('no.visitPeriod LIKE :visitPeriod')
            ->setParameters(['participantId' => $participantId, 'module' => $module, 'visitPeriod' => $visitPeriod . '%'])
            ->getQuery()
            ->getResult();
    }
}
