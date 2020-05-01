<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Pmi\WorkQueue\WorkQueue;

class SurveyController extends AbstractController
{
    protected static $routes = [
        ['surveys', '/participant/{participantId}/surveys']
    ];

    public function surveysAction($participantId, Application $app)
    {
        $surveyNames = [
            'TheBasics' => 'The Basics',
            'OverallHealth' => 'Overall Health',
            'Lifestyle' => 'Lifestyle',
            'PersonalMedicalHistory' => 'Personal Medical History',
            'FamilyHistory' => 'Family History',
            'HealthcareAccess' => 'Healthcare Access & Utilization'
        ];
        $surveys = [];
        foreach ($surveyNames as $survey => $label) {
            $responses = [];
            $questionnaireAnswers = $app['pmi.drc.participants']->getQuestionnaireAnswers($participantId, $survey);
            if ($questionnaireAnswers !== false) {
                foreach ($questionnaireAnswers as $response) {
                    $responses[] = (object)[
                        'authored' => $response->authored,
                        'version' => $response->version
                    ];
                }
            }
            $surveys[$survey] = $responses;
        }
        return $app['twig']->render('surveys/surveys.html.twig', [
            'surveys' => $surveys,
            'surveyNames' => $surveyNames
        ]);
    }
}
