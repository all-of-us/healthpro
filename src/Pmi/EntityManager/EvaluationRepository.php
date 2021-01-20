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
}
