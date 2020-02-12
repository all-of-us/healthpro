<?php

namespace Pmi\EntityManager;

class ProblemRepository extends DoctrineRepository
{

    public function getProblemsWithCommentsCount()
    {
        $problemsQuery = "
            SELECT p.*,
                   IFNULL(MAX(pc.created_ts), updated_ts) AS last_update_ts,
                   count(pc.comment) AS comment_count
            FROM problems p
            LEFT JOIN problem_comments pc ON p.id = pc.problem_id
            GROUP BY p.id
            ORDER BY IFNULL(MAX(pc.created_ts), updated_ts) DESC
        ";
        return $this->dbal->fetchAll($problemsQuery);
    }

    public function getParticipantProblemsWithCommentsCount($id)
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
            'participantId' => $id
        ]);
    }
}
