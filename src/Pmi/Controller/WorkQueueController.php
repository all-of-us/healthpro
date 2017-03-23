<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Pmi\Audit\Log;

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
        ]
    ];
    protected static $surveys = [
        'Sociodemographics' => 'Basics',
        'MedicalHistory' => 'Hist',
        'Medications' => 'Meds',
        'OverallHealth' => 'Health',
        'PersonalHabits' => 'Lifestyle',
        'FamilyHealth' => 'Family',
        'HealthcareAccess' => 'Access'
    ];

    protected function participantSummarySearch($params, $app)
    {
        // TODO: map site to organization
        $params['hpoId'] = 'PITT';
        $summaries = $app['pmi.drc.participants']->listParticipantSummaries($params);
        $results = [];
        foreach ($summaries as $summary) {
            if (isset($summary->resource)) {
                $results[] = $summary->resource;
            }
        }
        return $results;
    }

    public function indexAction(Application $app, Request $request)
    {
        $params = array_filter($request->query->all());
        $participants = $this->participantSummarySearch($params, $app);
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
        $participants = $this->participantSummarySearch($params, $app);
        $stream = function() use ($participants) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['This file contains information that is sensitive and confidential. Do not distribute either the file or its contents.']);
            fwrite($output, "\"\"\n");
            $headers = [
                'PMI ID',
                'Last Name',
                'First Name',
                'Date of Birth'
            ];
            foreach (self::$surveys as $survey => $label) {
                $headers[] = $label . ' PPI Survey Complete';
                $headers[] = $label . ' PPI Survey Completion Date';
            }
            $headers[] = 'Physical Measurements Status';
            $headers[] = 'Biospecimens';
            $headers[] = 'General Consent Status';
            $headers[] = 'General Consent Date';
            $headers[] = 'EHR Consent Status';
            $headers[] = 'Ethnicity';
            $headers[] = 'Race';
            $headers[] = 'Gender Identity';
            fputcsv($output, $headers);
            foreach ($participants as $participant) {
                $row = [
                    $participant->participantId,
                    $participant->lastName,
                    $participant->firstName,
                    date('m/d/Y', strtotime($participant->dateOfBirth)),
                ];
                foreach (self::$surveys as $survey => $label) {
                    $row[] = $participant->{"questionnaireOn{$survey}"} === 'SUBMITTED' ? 1 : 0;
                    if (isset($participant->{"questionnaireOn{$survey}Time"})) {
                        $row[] = date('m/d/Y', strtotime($participant->{"questionnaireOn{$survey}Time"}));
                    } else {
                        $row[] = '';
                    }
                }
                $row[] = $participant->physicalMeasurementsStatus === 'SUBMITTED' ? 1 : 0;
                $row[] = $participant->numBaselineSamplesArrived;
                $row[] = $participant->consentForStudyEnrollment === 'SUBMITTED' ? 1 : 0;
                $row[] = date('m/d/Y', strtotime($participant->consentForStudyEnrollmentTime));
                $row[] = $participant->consentForElectronicHealthRecords === 'SUBMITTED' ? 1 : 0;
                $row[] = $participant->ethnicity;
                $row[] = $participant->race;
                $row[] = $participant->genderIdentity;
                fputcsv($output, $row);
            }
            fwrite($output, "\"\"\n");
            fputcsv($output, ['Confidential Information']);
            fclose($output);
        };
        $filename = 'workqueue_' . date('Ymd-His') . '.csv';

        $app->log(Log::WORKQUEUE_EXPORT, [
            'filter' => $params,
            'site' => $app->getSiteId()
        ]);

        return $app->stream($stream, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }
}
