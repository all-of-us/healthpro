<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class PhoneNumber extends \Faker\Provider\en_US\PhoneNumber
{
    protected static $formats = ['{{areaCode}}-{{exchangeCode}}-####'];
}

class WorkQueueController extends AbstractController
{
    protected static $routes = [
        ['index', '/']
    ];

    // This will be replaced with an RDR Participant Summary API call when available
    protected function participantSummarySearch($params)
    {
        $results = [];
        $faker = \Faker\Factory::create();
        $faker->addProvider(new PhoneNumber($faker));
        for ($i = 0; $i < 20 + rand(0,15); $i++) {
            $enrollment = $faker->boolean(70) ? 'SUBMITTED' : 'UNSET';
            $physicalStatus = (($enrollment === 'SUBMITTED') & $faker->boolean(50)) ? 'SUBMITTED' : 'UNSET';
            $biobankStatus = ($enrollment === 'SUBMITTED') ? $faker->randomElement([0,1,2,3,4,5,6,7,7,7,7,7]) : 0;
            $results[] = [
                'firstName' => $faker->firstName,
                'lastName' => $faker->unique()->lastName,
                'phoneNumber' => $faker->phoneNumber,
                'emailAddress' => $faker->safeEmail,
                'consentForStudyEnrollment' => $enrollment,
                'physicalEvaluationStatus' => $physicalStatus,
                'biobankStatus' => $biobankStatus,
                'questionnaireOnFamilyHealth' => $faker->boolean(70) ? 'SUBMITTED' : 'UNSET',
                'questionnaireOnHealthcareAccess' => $faker->boolean(70) ? 'SUBMITTED' : 'UNSET',
                'questionnaireOnMedicalHistory' => $faker->boolean(70) ? 'SUBMITTED' : 'UNSET',
                'questionnaireOnMedications' => $faker->boolean(70) ? 'SUBMITTED' : 'UNSET',
                'questionnaireOnOverallHealth' => $faker->boolean(70) ? 'SUBMITTED' : 'UNSET',
                'questionnaireOnPersonalHabits' => $faker->boolean(70) ? 'SUBMITTED' : 'UNSET',
                'questionnaireOnSociodemographics' => $faker->boolean(70) ? 'SUBMITTED' : 'UNSET',
                'questionnaireOnSleep' => $faker->boolean(70) ? 'SUBMITTED' : 'UNSET'
            ];
        }
        usort($results, function($a, $b) {
            return strcasecmp($a['lastName'], $b['lastName']);
        });
        return $results;
    }

    public function indexAction(Application $app, Request $request)
    {
        $params = $request->query->all();
        $participants = $this->participantSummarySearch($params);
        return $app['twig']->render('workqueue/index.html.twig', [
            'participants' => $participants
        ]);
    }
}
