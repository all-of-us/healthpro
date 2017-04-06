<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Pmi\Audit\Log;
use Pmi\Entities\Participant;
use Pmi\Drc\CodeBook;

class WorkQueueController extends AbstractController
{
    protected static $name = 'workqueue';
    protected static $routes = [
        ['index', '/'],
        ['export', '/export.csv']
    ];
    protected static $filters = [
        'ageRange' => [
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
                '86+' => '86-'
            ]
        ],
        'genderIdentity' => [
            'label' => 'Gender identity',
            'options' => [
                'Man' => 'GenderIdentity_Man',
                'Woman' => 'GenderIdentity_Woman',
                'Non-binary' => 'GenderIdentity_NonBinary',
                'Transgender' => 'GenderIdentity_Transgender',
                'Other' => 'GenderIdentity_AdditionalOptions'
            ]
        ],
        'race' => [
            'label' => 'Race',
            'options' => [
                'American Indian / Alaska Native' => 'AMERICAN_INDIAN_OR_ALASKA_NATIVE',
                'Black or African American' => 'BLACK_OR_AFRICAN_AMERICAN',
                'Asian' => 'ASIAN',
                'Native Hawaiian or Other Pacific Islander' => 'NATIVE_HAWAIIAN_OR_OTHER_PACIFIC_ISLANDER',
                'White' => 'WHITE',
                'Hispanic, Latino, or Spanish' => 'HISPANIC_LATINO_OR_SPANISH',
                'Middle Eastern or North African' => 'MIDDLE_EASTERN_OR_NORTH_AFRICAN',
                'H/L/S and White' => 'HLS_AND_WHITE',
                'H/L/S and Black' => 'HLS_AND_BLACK',
                'H/L/S and one other race' => 'HLS_AND_ONE_OTHER_RACE',
                'H/L/S and more than one other race' => 'HLS_AND_MORE_THAN_ONE_OTHER_RACE',
                'More than one race' => 'MORE_THAN_ONE_RACE',
                'Other' => 'OTHER_RACE'
            ]
        ]
    ];
    protected static $surveys = [
        'TheBasics' => 'Basics',
        'MedicalHistory' => 'Hist',
        'Medications' => 'Meds',
        'OverallHealth' => 'Health',
        'Lifestyle' => 'Lifestyle',
        'FamilyHealth' => 'Family',
        'HealthcareAccess' => 'Access'
    ];

    protected function participantSummarySearch($params, $app)
    {
        // TODO: map site to organization
        $params['hpoId'] = 'PITT';

        // convert age range to dob filters - using string instead of array to support multiple params with same name
        if (isset($params['ageRange'])) {
            $ageRange = $params['ageRange'];
            unset($params['ageRange']);
            $params = http_build_query($params, null, '&', PHP_QUERY_RFC3986);

            $dateOfBirthFilters = CodeBook::ageRangeToDob($ageRange);
            foreach ($dateOfBirthFilters as $filter) {
                $params .= '&dateOfBirth=' . rawurlencode($filter);
            }
        }
        $summaries = $app['pmi.drc.participants']->listParticipantSummaries($params);
        $results = [];
        foreach ($summaries as $summary) {
            if (isset($summary->resource)) {
                $results[] = new Participant($summary->resource);
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

    protected static function csvDateFromObject($date)
    {
        return is_object($date) ? $date->format('m/d/Y') : '';
    }

    protected static function csvDateFromString($string)
    {
        if (!empty($string) && ($time = strtotime($string))) {
            return date('m/d/Y', $time);
        } else {
            return '';
        }
    }

    protected static function csvStatusFromSubmitted($status)
    {
        return $status === 'SUBMITTED' ? 1 : 0;
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
                'Date of Birth',
                'Language',
                'General Consent Status',
                'General Consent Date',
                'EHR Consent Status',
                'EHR Consent Date',
                'Withdrawal Status',
                'Address',
                'Email',
                'Phone',
                'Sex',
                'Gender Identity',
                'Sexual Orientation',
                'Race/Ethnicity',
                'Education',
                'Income'
            ];
            foreach (self::$surveys as $survey => $label) {
                $headers[] = $label . ' PPI Survey Complete';
                $headers[] = $label . ' PPI Survey Completion Date';
            }
            $headers[] = 'Physical Measurements Status';
            $headers[] = 'Biospecimens';
            fputcsv($output, $headers);
            foreach ($participants as $participant) {
                $row = [
                    $participant->id,
                    $participant->lastName,
                    $participant->firstName,
                    self::csvDateFromObject($participant->dob),
                    $participant->language,
                    self::csvStatusFromSubmitted($participant->consentForStudyEnrollment),
                    self::csvDateFromString($participant->consentForStudyEnrollmentTime),
                    self::csvStatusFromSubmitted($participant->consentForElectronicHealthRecords),
                    self::csvDateFromString($participant->consentForElectronicHealthRecordsTime),
                    $participant->withdrawalStatus == 'NO_USE' ? '1' : '0',
                    $participant->getAddress(),
                    $participant->email,
                    $participant->phoneNumber,
                    $participant->sex,
                    $participant->genderIdentity,
                    $participant->sexualOrientation,
                    $participant->race,
                    $participant->education,
                    $participant->income
                ];
                foreach (self::$surveys as $survey => $label) {
                    $row[] = self::csvStatusFromSubmitted($participant->{"questionnaireOn{$survey}"});
                    $row[] = self::csvDateFromString($participant->{"questionnaireOn{$survey}Time"});
                }
                $row[] = self::csvStatusFromSubmitted($participant->physicalMeasurementsStatus);
                $row[] = $participant->numBaselineSamplesArrived;
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
