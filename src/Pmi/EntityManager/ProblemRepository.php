<?php

namespace Pmi\EntityManager;

/**
 * @deprecated 2020-12-21 Left in place for Silex Workqueue and Participant views.
 */
class ProblemRepository extends DoctrineRepository
{
    public function getParticipantProblemsWithCommentsCount($participantId)
    {
        $problemsQuery = "
            SELECT p.id,
                   p.updated_ts,
                   p.finalized_ts,
                   MAX(pc.created_ts) as last_comment_ts,
                   count(pc.comment) as comment_count
            FROM problems p
            LEFT JOIN problem_comments pc on p.id = pc.problem_id
            WHERE p.participant_id = :participantId
            GROUP BY p.id
            ORDER BY IFNULL(MAX(pc.created_ts), updated_ts) DESC
        ";
        return $this->dbal->fetchAll($problemsQuery, [
            'participantId' => $participantId
        ]);
    }
}
