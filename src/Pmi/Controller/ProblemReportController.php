<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Pmi\Audit\Log;

class problemReportController extends ProblemController
{
    protected static $name = 'problem';
    protected static $routes = [
        ['reports', '/reports'],
        ['details', '/details/{problemId}']
    ];

    public function reportsAction(Application $app, Request $request)
    {
        $query = "SELECT p.*,
                    IFNULL(MAX(pc.created_ts), updated_ts) AS last_update_ts,
                    count(pc.comment) AS comment_count
                    FROM problems p LEFT JOIN problem_comments pc ON p.id = pc.problem_id
                    WHERE p.finalized_ts IS NOT NULL
                    GROUP BY p.id
                    ORDER BY IFNULL(MAX(pc.created_ts), updated_ts) DESC";
        $problems = $app['db']->fetchAll($query);

        return $app['twig']->render('problem-reports.html.twig', [
            'problems' => $problems,
            'optionsValue' => [self::RELATED_BASELINE, self::UNRELATED_BASELINE, self::OTHER],
            'optionsText' => $this->problemTypeOptions
        ]);
    }

    public function detailsAction($problemId, Application $app, Request $request)
    {
        $problem = $app['em']->getRepository('problems')->fetchOneBy([
            'id' => $problemId
        ]);
        if (!$problem) {
            $app->abort(404);
        }
        if ($problem['problem_type'] == self::RELATED_BASELINE) {
            $problem['problem_type'] = $this->problemTypeOptions[0];
        } elseif ($problem['problem_type'] == self::UNRELATED_BASELINE) {
            $problem['problem_type'] = $this->problemTypeOptions[1];
        } else {
            $problem['problem_type'] = $this->problemTypeOptions[2];
        }

        $comments = $app['em']->getRepository('problem_comments')->fetchBy(
            ['problem_id' => $problemId],
            ['id' => 'DESC']
        );

        return $app['twig']->render('problem-details.html.twig', [
            'problem' => $problem,
            'comments' => $comments
        ]);
    }
}
