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
    protected $related = self::RELATED_BASELINE;
    protected $unrelated = self::UNRELATED_BASELINE;
    protected $other = self::OTHER;

    public function reportsAction(Application $app, Request $request)
    {
        $query = "SELECT p.*,
                    CASE 
                        WHEN p.problem_type = '{$this->related}' THEN '{$this->problemTypeOptions[0]}'
                        WHEN p.problem_type = '{$this->unrelated}' THEN '{$this->problemTypeOptions[1]}'
                        ELSE '{$this->problemTypeOptions[2]}'
                    END AS problem_type,
                    MAX(pc.created_ts) AS last_comment_ts,
                    count(pc.comment) AS comment_count
                    FROM problems p LEFT JOIN problem_comments pc ON p.id = pc.problem_id
                    GROUP BY p.id
                    ORDER BY IFNULL(MAX(pc.created_ts), updated_ts) DESC";
        $problems = $app['db']->fetchAll($query);

        return $app['twig']->render('problem-reports.html.twig', [
            'problems' => $problems
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
        if ($problem['problem_type'] == $this->related) {
            $problem['problem_type'] = $this->problemTypeOptions[0];
        } elseif ($problem['problem_type'] == $this->unrelated) {
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
