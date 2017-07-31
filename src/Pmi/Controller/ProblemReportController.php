<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Pmi\Audit\Log;

class problemReportController extends ProblemController
{
    protected static $name = 'problem';
    protected static $routes = [
        ['reports', '/reports']
    ];
    protected $related = self::RELATED_BASELINE;
    protected $unrelated = self::UNRELATED_BASELINE;
    protected $other = self::OTHER;
    protected $relatedText = self::RELATED_BASELINE_TEXT;
    protected $unrelatedText = self::UNRELATED_BASELINE_TEXT;
    protected $otherText = self::OTHER_TEXT;

    public function reportsAction(Application $app, Request $request)
    {
        $query = "SELECT p.*,
                    CASE 
                        WHEN p.problem_type = '{$this->related}' THEN '{$this->relatedText}'
                        WHEN p.problem_type = '{$this->unrelated}' THEN '{$this->unrelatedText}'
                        ELSE '{$this->otherText}'
                    END AS problem_type,
                    MAX(pc.created_ts) AS last_comment_ts,
                    count(pc.comment) AS comment_count
                    FROM problems p LEFT JOIN problem_comments pc ON p.id = pc.problem_id
                    GROUP BY p.id
                    ORDER BY IFNULL(MAX(pc.created_ts), updated_ts) DESC";
        $problems = $app['em']->getRepository('problems')->fetchByRawSQL($query);

        return $app['twig']->render('problem-reports.html.twig', [
            'problems' => $problems
        ]);
    }
}
