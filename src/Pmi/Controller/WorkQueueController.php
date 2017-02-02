<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Pmi\Audit\Log;

class PhoneNumber extends \Faker\Provider\en_US\PhoneNumber
{
    protected static $formats = ['{{areaCode}}-{{exchangeCode}}-####'];
}

class WorkQueueController extends AbstractController
{
    protected static $name = 'workqueue';
    protected static $routes = [
        ['index', '/'],
        ['export', '/export.csv']
    ];
    protected static $filters = [
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
        ],
        'ppi' => [
            'label' => 'PPI Surveys Completed',
            'options' => [
                'None' => 'NONE',
                'Some' => 'SOME',
                'All' => 'ALL'
            ]
        ],
        'biobank' => [
            'label' => 'Biospecimens banked',
            'options' => [
                'None' => 'NONE',
                'Some' => 'SOME',
                'All' => 'ALL'
            ]
        ]
    ];
    protected static $surveys = [
        'Sociodemographics' => 'Demo',
        'MedicalHistory' => 'Hist',
        'Medications' => 'Meds',
        'OverallHealth' => 'Health',
        'PersonalHabits' => 'Habits',
        'FamilyHealth' => 'Family',
        'HealthcareAccess' => 'Access',
        'Sleep' => 'Sleep'
    ];

    // This will be replaced with an RDR Participant Summary API call when available
    protected function participantSummarySearch($params)
    {
        $results = [];
        $faker = \Faker\Factory::create();
        $faker->addProvider(new PhoneNumber($faker));
        $count = 100 + rand(0,20);
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
            $count = round($count * 0.5);
        }
        if (isset($params['biobank'])) {
            $count = round($count * 0.3);
        }
        if (isset($params['ppi'])) {
            $count = round($count * 0.3);
        }
        for ($i = 0; $i < $count; $i++) {
            $biobankStatus = $faker->randomElement([0,0,0,0,1,2,3,4,5,6,7,7,7,7,7,7]);
            if (isset($params['biobank'])) {
                switch ($params['biobank']) {
                    case 'ALL':
                        $biobankStatus = 7;
                        break;
                    case 'SOME':
                        $biobankStatus = $faker->numberBetween(1,6);
                        break;
                    default:
                        $biobankStatus = 0;
                }
            }
            $physicalStatus = $faker->boolean(50) ? 'SUBMITTED' : 'UNSET';
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
            $withdrawalStatus = $faker->randomElement(array_merge(
                array_fill(0, 16, 'Enrolled'),
                [
                    'Suspension - No Contact',
                    'Suspension - No Access',
                    'Withdrawal - No Use',
                    'Withdrawal - No Use After Death'
                ]
            ));
            $row = [
                'firstName' => $firstName,
                'lastName' => $faker->unique()->lastName,
                'preferredContact' => $withdrawalStatus === 'Enrolled' ? $faker->randomElement(['EMAIL', 'EMAIL', 'EMAIL', 'PHONE', 'PHONE', 'MAIL', 'NO_CONTACT']) : 'NO_CONTACT',
                'phoneNumber' => $withdrawalStatus === 'Enrolled' ? $faker->phoneNumber : '',
                'emailAddress' => $withdrawalStatus === 'Enrolled' ? $faker->safeEmail : '',
                'mailingAddress' => $withdrawalStatus === 'Enrolled' ? $faker->address : '',
                'physicalEvaluationStatus' => $physicalStatus,
                'biobankStatus' => $biobankStatus,
                'withdrawalStatus' => $withdrawalStatus,
                'pmiId' => 'P' . $faker->randomNumber(9),
                'consentDate' => $faker->dateTimeBetween('-1 year', 'now')
            ];
            foreach (array_keys(self::$surveys) as $survey) {
                if (isset($params['ppi']) && $params['ppi'] === 'NONE') {
                    $row["questionnaireOn{$survey}"] = 'UNSET';
                } elseif (isset($params['ppi']) && $params['ppi'] === 'ALL') {
                    $row["questionnaireOn{$survey}"] = 'SUBMITTED';
                } else {
                    $row["questionnaireOn{$survey}"] = $faker->boolean(70) ? 'SUBMITTED' : 'UNSET';
                }
            }
            $results[] = $row;
        }
        usort($results, function($a, $b) {
            return strcasecmp($a['lastName'], $b['lastName']);
        });
        return $results;
    }

    public function indexAction(Application $app, Request $request)
    {
        $params = array_filter($request->query->all());
        $participants = $this->participantSummarySearch($params);
        return $app['twig']->render('workqueue/index.html.twig', [
            'filters' => self::$filters,
            'surveys' => self::$surveys,
            'participants' => $participants,
            'params' => $params
        ]);
    }

    public function exportAction(Application $app, Request $request)
    {
        $params = array_filter($request->query->all());
        $participants = $this->participantSummarySearch($params);
        $stream = function() use ($participants) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['This file contains information that is sensitive and confidential. Do not distribute either the file or its contents.']);
            fwrite($output, "\"\"\n");
            $headers = [
                'PMI ID',
                'Last Name',
                'First Name',
                'Preferred Contact Method',
                'Phone Number',
                'Email Address',
                'Mailing Address',
                'Consent Date'
            ];
            foreach (self::$surveys as $survey => $label) {
                $headers[] = $label . ' PPI Survey Completion';
            }
            $headers[] = 'Physical Measurements Status';
            $headers[] = 'Biobank Samples';
            $headers[] = 'Withdrawal Status';
            fputcsv($output, $headers);
            foreach ($participants as $participant) {
                $row = [
                    $participant['pmiId'],
                    $participant['lastName'],
                    $participant['firstName'],
                    $participant['preferredContact'],
                    $participant['phoneNumber'],
                    $participant['emailAddress'],
                    str_replace("\n", ', ', trim($participant['mailingAddress'])),
                    $participant['consentDate']->format('m/d/Y')
                ];
                foreach (self::$surveys as $survey => $label) {
                    $row[] = $participant["questionnaireOn{$survey}"] === 'SUBMITTED' ? 1 : 0;
                }
                $row[] = $participant['physicalEvaluationStatus'] === 'SUBMITTED' ? 1 : 0;
                $row[] = $participant['biobankStatus'];
                $row[] = $participant['withdrawalStatus'];
                fputcsv($output, $row);
            }
            fwrite($output, "\"\"\n");
            fputcsv($output, ['Confidential Information']);
            fclose($output);
        };

        $filename = 'workqueue_' . date('Ymd-His') . '.csv';

        $app->log(Log::WORKQUEUE_EXPORT, array($params, $app->getSite()));


        return $app->stream($stream, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }
}
