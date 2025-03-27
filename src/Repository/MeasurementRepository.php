<?php

namespace App\Repository;

use App\Entity\Measurement;
use App\Entity\MissingNotificationLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
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

    public function getMostRecentFinalizedNonNullWeight(string $participantId, bool $isPediatric): Measurement|null
    {
        if (!$isPediatric) {
            return null;
        }
        $parentIds = $this->createQueryBuilder('m')
            ->select('m.parentId')
            ->where('m.parentId is not null')
            ->andWhere('m.participantId = :participantId')
            ->setParameter('participantId', $participantId)
            ->getQuery()
            ->getResult();
        $query = $this->createQueryBuilder('m')
            ->where('m.participantId = :participantId')
            ->andWhere('m.finalizedTs is not null')
            ->orderBy('m.finalizedTs', 'DESC')
            ->leftJoin('m.history', 'mh')
            ->setParameter('participantId', $participantId);
        if (!empty($parentIds)) {
            $query->andWhere('m.id not in (:parentIds)')
            ->setParameter('parentIds', $parentIds);
        }
        $results = $query->getQuery()->getResult();
        $cancelledMeasurements = [];
        foreach ($results as $result) {
            if (in_array($result->getId(), $cancelledMeasurements)) {
                continue;
            }
            if ($result->getHistory() && $result->getHistory()->getType() === Measurement::EVALUATION_CANCEL) {
                $cancelledMeasurements[] = $result->getId();
                continue;
            }
            $measurementData = json_decode($result->getData(), true);
            if ($measurementData['weight'] && isset($measurementData['weight'][0]) && $measurementData['weight'][0] > 0) {
                return $result;
            }
        }
        return null;
    }

    public function getProtocolModificationCount($startDate, $endDate, $modificationType, $minAge, $maxAge): array
    {
        $query = 'SELECT count(*) as count, q.modification as modificationType FROM
                    (
                        SELECT JSON_UNQUOTE(JSON_EXTRACT(data, :jsonPath1)) AS modification FROM evaluations where age_in_months > :minAge1 and age_in_months < :maxAge1 UNION ALL
                        SELECT JSON_UNQUOTE(JSON_EXTRACT(data, :jsonPath2)) AS modification FROM evaluations where age_in_months > :minAge2 and age_in_months < :maxAge2 UNION ALL
                        SELECT JSON_UNQUOTE(JSON_EXTRACT(data, :jsonPath3)) AS modification FROM evaluations where age_in_months > :minAge3 and age_in_months < :maxAge3
                    ) q
                    WHERE q.modification IS NOT NULL
                    AND q.modification <> \'\'
                    GROUP BY q.modification';
        $em = $this->getEntityManager();
        $stmt = $em->getConnection()->prepare($query);
        $modificationTypeString1 = "$.\"$modificationType\"[0]";
        $modificationTypeString2 = "$.\"$modificationType\"[1]";
        $modificationTypeString3 = "$.\"$modificationType\"[2]";
        $stmt->bindParam('jsonPath1', $modificationTypeString1, ParameterType::STRING);
        $stmt->bindParam('minAge1', $minAge, ParameterType::INTEGER);
        $stmt->bindParam('maxAge1', $maxAge, ParameterType::INTEGER);
        $stmt->bindParam('jsonPath2', $modificationTypeString2, ParameterType::STRING);
        $stmt->bindParam('minAge2', $minAge, ParameterType::INTEGER);
        $stmt->bindParam('maxAge2', $maxAge, ParameterType::INTEGER);
        $stmt->bindParam('jsonPath3', $modificationTypeString3, ParameterType::STRING);
        $stmt->bindParam('minAge3', $minAge, ParameterType::INTEGER);
        $stmt->bindParam('maxAge3', $maxAge, ParameterType::INTEGER);
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }

    public function getActiveAlertsReportData($minAge, $maxAge): array
    {
        $query = $this->createQueryBuilder('m')
            ->andWhere('m.ageInMonths >= :minAge')
            ->andWhere('m.ageInMonths <= :maxAge')
            ->setParameter('minAge', $minAge)
            ->setParameter('maxAge', $maxAge)
            ->getQuery();
        return $query->getResult();
    }

    public function getCompleteMeasurementsForPediatrictotalsReport(string $field, int $minAge, int $maxAge)
    {
        $query = '
        select count(DISTINCT participant_id) as participant_count from(
                 SELECT 
                     participant_id,
                    CASE 
                        WHEN JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField0)) = "null" THEN NULL
                        WHEN JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField0)) = "false" THEN NULL
                        ELSE JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField0))
                    END AS field1,
                    CASE 
                        WHEN JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField1)) = "null" THEN NULL
                        WHEN JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField1)) = "false" THEN NULL
                        ELSE JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField1))
                    END AS field2
                 from evaluations
                 where age_in_months >= :minAge
                   and age_in_months <= :maxAge
             ) jsonvalues
        where field1 is not null and field2 is not null';
        $fieldString0 = "$.\"$field\"[0]";
        $fieldString1 = "$.\"$field\"[1]";
        $em = $this->getEntityManager();
        $stmt = $em->getConnection()->prepare($query);
        $stmt->bindParam('jsonField0', $fieldString0, ParameterType::STRING);
        $stmt->bindParam('jsonField1', $fieldString1, ParameterType::STRING);
        $stmt->bindParam('minAge', $minAge, ParameterType::INTEGER);
        $stmt->bindParam('maxAge', $maxAge, ParameterType::INTEGER);
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }

    public function getAnyMeasurementsForPediatrictotalsReport(string $field, int $minAge, int $maxAge)
    {
        $query = '
        select count(DISTINCT participant_id) as participant_count from(
                 SELECT 
                     participant_id,
                    CASE 
                        WHEN JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField0)) = "null" THEN NULL
                        WHEN JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField0)) = "false" THEN NULL
                        ELSE JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField0))
                    END AS field1,
                    CASE 
                        WHEN JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField1)) = "null" THEN NULL
                        WHEN JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField1)) = "false" THEN NULL
                        ELSE JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField1))
                    END AS field2,
                    CASE 
                        WHEN JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField2)) = "null" THEN NULL
                        WHEN JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField2)) = "false" THEN NULL
                        ELSE JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField2))
                    END AS field3
                 from evaluations
                 where age_in_months >= :minAge
                   and age_in_months <= :maxAge
             ) jsonvalues
        where field1 is not null or field2 is not null or field3 is not null';
        $fieldString0 = "$.\"$field\"[0]";
        $fieldString1 = "$.\"$field\"[1]";
        $fieldString2 = "$.\"$field\"[2]";
        $em = $this->getEntityManager();
        $stmt = $em->getConnection()->prepare($query);
        $stmt->bindParam('jsonField0', $fieldString0, ParameterType::STRING);
        $stmt->bindParam('jsonField1', $fieldString1, ParameterType::STRING);
        $stmt->bindParam('jsonField2', $fieldString2, ParameterType::STRING);
        $stmt->bindParam('minAge', $minAge, ParameterType::INTEGER);
        $stmt->bindParam('maxAge', $maxAge, ParameterType::INTEGER);
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }

    public function getThridMeasurementsForPediatrictotalsReport(string $field, int $minAge, int $maxAge)
    {
        $query = '
        select count(DISTINCT participant_id) as participant_count from(
                 SELECT 
                     participant_id,
                    CASE 
                        WHEN JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField2)) = "null" THEN NULL
                        WHEN JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField2)) = "false" THEN NULL
                        ELSE JSON_UNQUOTE(JSON_EXTRACT(data, :jsonField2))
                    END AS field3
                 from evaluations
                 where age_in_months >= :minAge
                   and age_in_months <= :maxAge
             ) jsonvalues
        where field3 is not null';
        $fieldString2 = "$.\"$field\"[2]";
        $em = $this->getEntityManager();
        $stmt = $em->getConnection()->prepare($query);
        $stmt->bindParam('jsonField2', $fieldString2, ParameterType::STRING);
        $stmt->bindParam('minAge', $minAge, ParameterType::INTEGER);
        $stmt->bindParam('maxAge', $maxAge, ParameterType::INTEGER);
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }

    public function getMissingSexAtBirthPediatricMeasurements(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.sexAtBirth is null')
            ->andWhere('m.version like :version')
            ->setParameter('version', '%peds%')
            ->setMaxResults(10)
            ->orderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
