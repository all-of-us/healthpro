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
    protected static $name = 'workqueue';

    protected static $routes = [
        ['index', '/']
    ];

    // This will be replaced with an RDR Participant Summary API call when available
    protected function participantSummarySearch($params)
    {
        $results = [];
        $faker = \Faker\Factory::create();
        $faker->addProvider(new PhoneNumber($faker));
        $count = 90 + rand(0,20);
        $params = array_filter($params);
        if (isset($params['age'])) {
            $count = round($count * 0.3);
        }
        if (isset($params['gender'])) {
            $count = round($count * 0.5);
        }
        if (isset($params['ethnicity'])) {
            $count = round($count * 0.5);
        }
        if (isset($params['race'])) {
            $count = round($count * 0.7);
        }
        for ($i = 0; $i < $count + rand(0,10); $i++) {
            $enrollment = $faker->boolean(70) ? 'SUBMITTED' : 'UNSET';
            $physicalStatus = (($enrollment === 'SUBMITTED') & $faker->boolean(50)) ? 'SUBMITTED' : 'UNSET';
            $biobankStatus = ($enrollment === 'SUBMITTED') ? $faker->randomElement([0,1,2,3,4,5,6,7,7,7,7,7]) : 0;
            if (isset($params['gender'])) {
                if ($params['gender'] === 'MALE') {
                    $firstName = $faker->firstNameMale;
                } elseif ($params['gender'] === 'FEMALE') {
                    $firstName = $faker->firstNameFemale;
                } else {
                    $firstName = $faker->firstName;
                }
            } else {
                $firstName = $faker->firstName;
            }
            $results[] = [
                'firstName' => $firstName,
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
        $filters = [
            'age' => [
                'label' => 'Age',
                'options' => [
                    '0-17' => '0-17',
                    '18-25' => '18-25',
                    '26-35' => '26-35',
                    '36-45' => '36-45',
                    '46-55' => '46-55',
                    '56-65' => '56-65',
                    '66-75' => '66-75',
                    '76-85' => '76-85',
                    '86+' => '86+'
                ]
            ],
            'gender' => [
                'label' => 'Gender identity',
                'options' => [
                    'Female' => 'FEMALE',
                    'Male' => 'MALE',
                    'Female to male transgender' => 'FEMALE_TO_MALE_TRANSGENDER',
                    'Male to female transgender' => 'MALE_TO_FEMALE_TRANSGENDER',
                    'Intersex' => 'INTERSEX',
                    'Other' => 'OTHER'
                ]
            ],
            'ethnicity' => [
                'label' => 'Ethnicity',
                'options' => [
                    'Hispanic' => 'HISPANIC',
                    'Non Hispanic' => 'NON_HISPANIC'
                ]
            ],
            'race' => [
                'label' => 'Race',
                'options' => [
                    'American Indian or Alaska Native' => 'AMERICAN_INDIAN_OR_ALASKA_NATIVE',
                    'Black or African American' => 'BLACK_OR_AFRICAN_AMERICAN',
                    'Asian' => 'ASIAN',
                    'Native Hawaiian or other Pacific Islander' => 'NATIVE_HAWAIIAN_OR_OTHER_PACIFIC_ISLANDER',
                    'White' => 'WHITE',
                    'Other race' => 'OTHER_RACE'
                ]
            ]
        ];
        return $app['twig']->render('workqueue/index.html.twig', [
            'filters' => $filters,
            'participants' => $participants,
            'params' => $params
        ]);
    }
}
