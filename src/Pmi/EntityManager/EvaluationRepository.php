<?php

namespace Pmi\EntityManager;

use Pmi\Evaluation\Evaluation;

class EvaluationRepository extends DoctrineRepository
{
    public function getEvaluationsWithHistory($participantId)
    {
        $evaluationsQuery = "
            SELECT e.*,
                   eh.evaluation_id AS eh_evaluation_id,
                   eh.user_id AS eh_user_id,
                   eh.site AS eh_site,
                   eh.type AS eh_type,
                   eh.reason AS eh_reason,
                   eh.created_ts AS eh_created_ts
            FROM evaluations e
            LEFT JOIN evaluations_history eh ON e.history_id = eh.id
            WHERE e.id NOT IN (SELECT parent_id FROM evaluations WHERE parent_id IS NOT NULL)
              AND e.participant_id = :participant_id
            ORDER BY e.id DESC
        ";
        return $this->dbal->fetchAll($evaluationsQuery, [
            'participant_id' => $participantId
        ]);
    }

    public function getSiteUnfinalizedEvaluations($siteId)
    {
        $evaluationsQuery = "
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
        ";
        return $this->dbal->fetchAll($evaluationsQuery, [
            'site' => $siteId,
            'type' => Evaluation::EVALUATION_CANCEL
        ]);
    }

    public function getSiteRecentModifiedEvaluations($siteId)
    {
        $evaluationsQuery = "
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
        ";
        return $this->dbal->fetchAll($evaluationsQuery, [
            'site' => $siteId,
            'type' => Evaluation::EVALUATION_CANCEL
        ]);
    }
}
