<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Pmi\Audit\Log;

class ProblemReportController extends ProblemController
{
    protected static $name = 'problem';
    protected static $routes = [
        ['reports', '/reports'],
        ['details', '/details/{problemId}']
    ];

    public function reportsAction(Application $app, Request $request)
    {
        $problems = $app['em']->getRepository('problem_repository')->getProblemsWithCommentsCount();

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
