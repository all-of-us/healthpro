<?php

namespace App\Repository;

use App\Entity\Measurement;
use App\Entity\MissingNotificationLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Measurement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Measurement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Measurement[]    findAll()
 * @method Measurement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MeasurementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Measurement::class);
    }

    /**
     * @return Measurement[] Returns an array of Measurement objects
     */
    public function getMissingMeasurements()
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.history', 'mh')
            ->where('m.finalizedTs is not null')
            ->andWhere('m.rdrId is null')
            ->andWhere('mh.type != :type OR mh.type is null')
            ->setParameter('type', 'cancel')
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array
     */
    public function getSiteUnfinalizedEvaluations($siteId)
    {
        $evaluationsQuery = '
            SELECT e.*,
                   eh.evaluation_id AS eh_evaluation_id,
                   eh.user_id AS eh_user_id,
                   eh.site AS eh_site,
                   eh.type AS eh_type,
                   eh.reason AS eh_reason,
                   eh.created_ts AS eh_created_ts
            FROM evaluations e
            LEFT JOIN evaluations_history eh ON e.history_id = eh.id
            WHERE e.site = :site
              AND e.finalized_ts IS NULL
              AND (eh.type != :type
              OR eh.type IS NULL)
            ORDER BY e.created_ts DESC
        ';
        return $this->getEntityManager()->getConnection()->fetchAll($evaluationsQuery, [
            'site' => $siteId,
            'type' => Measurement::EVALUATION_CANCEL
        ]);
    }

    /**
     * @return array
     */
    public function getSiteRecentModifiedEvaluations($siteId)
    {
        $evaluationsQuery = '
            SELECT e.*,
                   eh.evaluation_id AS eh_order_id,
                   eh.user_id AS eh_user_id,
                   eh.site AS eh_site,
                   eh.type AS eh_type,
                   eh.created_ts AS eh_created_ts,
                   IFNULL (eh.created_ts, e.updated_ts) as modified_ts
            FROM evaluations e
            LEFT JOIN evaluations_history eh ON e.history_id = eh.id
            WHERE e.site = :site
              AND (eh.type = :type
              OR (eh.type IS NULL
              AND e.parent_id IS NOT NULL))
              AND e.id NOT IN (SELECT parent_id FROM evaluations WHERE parent_id IS NOT NULL)
              AND (eh.created_ts >= UTC_TIMESTAMP() - INTERVAL 7 DAY OR e.updated_ts >= UTC_TIMESTAMP() - INTERVAL 7 DAY)
            ORDER BY modified_ts DESC
        ';
        return $this->getEntityManager()->getConnection()->fetchAll($evaluationsQuery, [
            'site' => $siteId,
            'type' => Measurement::EVALUATION_CANCEL
        ]);
    }

    public function getUnloggedMissingMeasurements(): array
    {
        $evaluationsQuery = 'SELECT id FROM evaluations WHERE id NOT IN (SELECT record_id FROM missing_notifications_log WHERE type = :type) AND finalized_ts IS NOT NULL AND rdr_id IS NULL';
        return $this->getEntityManager()->getConnection()->fetchAll($evaluationsQuery, [
            'type' => MissingNotificationLog::MEASUREMENT_TYPE
        ]);
    }

    public function getMeasurement($measurementId, $participantId)
    {
        $parentIds = $this->createQueryBuilder('m')
            ->select('m.parentId')
            ->where('m.parentId is not null')
            ->getQuery()
            ->getResult()
        ;
        $queryParams = ['measurementId' => $measurementId, 'participantId' => $participantId];
        $queryBuilder = $this->createQueryBuilder('m')
            ->where('m.id = :measurementId')
            ->andWhere('m.participantId = :participantId');
        if (!empty($parentIds)) {
            $queryBuilder->andWhere($queryBuilder->expr()->notIn('m.id', ':parentIds'));
            $queryParams['parentIds'] = $parentIds;
        }
        $measurement = $queryBuilder
            ->setParameters($queryParams)
            ->getQuery()
            ->getResult()
        ;
        return !empty($measurement) ? $measurement[0] : null;
    }

    public function getMeasurementsWithoutParent($participantId): array
    {
        $parentIds = $this->createQueryBuilder('m')
            ->select('m.parentId')
            ->where('m.parentId is not null')
            ->getQuery()
            ->getResult();
        $queryParams = ['participantId' => $participantId];
        $queryBuilder = $this->createQueryBuilder('m')
            ->where('m.participantId = :participantId');
        if (!empty($parentIds)) {
            $queryBuilder->andWhere($queryBuilder->expr()->notIn('m.id', ':parentIds'));
            $queryParams['parentIds'] = $parentIds;
        }
        return $queryBuilder
            ->setParameters($queryParams)
            ->orderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getMostRecentMeasurementWithoutParent($participantId): Measurement|null
    {
        $parentIds = $this->createQueryBuilder('m')
            ->select('m.parentId')
            ->where('m.parentId is not null')
            ->getQuery()
            ->getResult();
        $queryParams = ['participantId' => $participantId];
        $queryBuilder = $this->createQueryBuilder('m')
            ->where('m.participantId = :participantId');
        if (!empty($parentIds)) {
            $queryBuilder->andWhere($queryBuilder->expr()->notIn('m.id', ':parentIds'));
            $queryParams['parentIds'] = $parentIds;
        }
        return $queryBuilder
            ->setParameters($queryParams)
            ->setMaxResults(1)
            ->orderBy('m.finalizedTs', 'DESC')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
