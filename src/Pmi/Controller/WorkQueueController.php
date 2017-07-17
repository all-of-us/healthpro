<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Pmi\Audit\Log;
use Pmi\Entities\Participant;
use Pmi\Drc\CodeBook;

class WorkQueueController extends AbstractController
{
    const LIMIT_DEFAULT = 1000;
    const LIMIT_EXPORT = 5000;

    protected static $name = 'workqueue';
    protected static $routes = [
        ['index', '/'],
        ['export', '/export.csv']
    ];
    protected static $filters = [
        'withdrawalStatus' => [
            'label' => 'Withdrawal Status',
            'options' => [
                'Withdrawn' => 'NO_USE',
                'Not withdrawn' => 'NOT_WITHDRAWN'
            ]
        ],
        'consentForElectronicHealthRecords' => [
            'label' => 'EHR Consent Status',
            'options' => [
                'Consented' => 'SUBMITTED',
                'Not consented' => 'UNSET'
            ]
        ],
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
            'label' => 'Gender Identity',
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
    // These are currently not working in the RDR
    protected static $filtersDisabled = [
        'language' => [
            'label' => 'Language',
            'options' => [
                'English' => 'SpokenWrittenLanguage_English',
                'Spanish' => 'SpokenWrittenLanguage_Spanish'
            ]
        ],
        'recontactMethod' => [
            'label' => 'Contact Method',
            'options' => [
                'House Phone' => 'RecontactMethod_HousePhone',
                'Cell Phone' => 'RecontactMethod_CellPhone',
                'Email' => 'RecontactMethod_Email',
                'Physical Address' => 'RecontactMethod_Address'
            ]
        ],
        'sex' => [
            'label' => 'Sex',
            'options' => [
                'Male' => 'SexAtBirth_Male',
                'Female' => 'SexAtBirth_Female',
                'Intersex' => 'SexAtBirth_Intersex'
            ]
        ],
        'sexualOrientation' => [
            'label' => 'Sexual Orientation',
            'options' => [
                'Straight' => 'SexualOrientation_Straight',
                'Gay' => 'SexualOrientation_Gay',
                'Lesbian' => 'SexualOrientation_Lesbian',
                'Bisexual' => 'SexualOrientation_Bisexual',
                'Other' => 'SexualOrientation_None'
            ]
        ],
        // ne not supported with enums
        'race' => [
            'label' => 'Race',
            'options' => [
                'White' => 'WHITE',
                'Not white' => 'neWHITE'
            ]
        ]
    ];
    protected static $surveys = [
        'TheBasics' => 'Basics',
        'OverallHealth' => 'Health',
        'Lifestyle' => 'Lifestyle',
        'MedicalHistory' => 'Hist',
        'Medications' => 'Meds',
        'FamilyHealth' => 'Family',
        'HealthcareAccess' => 'Access'
    ];
    protected static $samples = [
        '1SST8' => '8 mL SST',
        '1PST8' => '8 mL PST',
        '1HEP4' => '4 mL Na-Hep',
        '1ED04' => '4 mL EDTA',
        '1ED10' => '1st 10 mL EDTA',
        '2ED10' => '2nd 10 mL EDTA',
        '1UR10' => 'Urine 10 mL',
        '1SAL' => 'Saliva'
    ];
    protected $rdrError = false;

    protected function participantSummarySearch($organization, &$params, $app)
    {
        $rdrParams = [];
        if (isset($params['withdrawalStatus']) && $params['withdrawalStatus'] === 'NO_USE') {
            foreach ($params as $key => $value) {
                if ($key === 'withdrawalStatus' || $key === 'organization') {
                    continue;
                }
                unset($params[$key]);
            }
        } else {
            $rdrParams['_sort:desc'] = 'consentForStudyEnrollmentTime';
        }
        $rdrParams = array_merge($rdrParams, $params);
        $rdrParams['hpoId'] = $organization;
        if (!isset($rdrParams['_count'])) {
            $rdrParams['_count'] = self::LIMIT_DEFAULT;
        }

        // convert age range to dob filters - using string instead of array to support multiple params with same name
        if (isset($rdrParams['ageRange'])) {
            $ageRange = $rdrParams['ageRange'];
            unset($rdrParams['ageRange']);
            $rdrParams = http_build_query($rdrParams, null, '&', PHP_QUERY_RFC3986);

            $dateOfBirthFilters = CodeBook::ageRangeToDob($ageRange);
            foreach ($dateOfBirthFilters as $filter) {
                $rdrParams .= '&dateOfBirth=' . rawurlencode($filter);
            }
        }
        $results = [];
        try {
            $summaries = $app['pmi.drc.participants']->listParticipantSummaries($rdrParams);
            foreach ($summaries as $summary) {
                if (isset($summary->resource)) {
                    $results[] = new Participant($summary->resource);
                }
            }
        } catch (\Exception $e){
            $this->rdrError = true;
        }
        return $results;
    }

    public function indexAction(Application $app, Request $request)
    {
        $organization = $app->getSiteOrganization();
        if (!$organization) {
            return $app['twig']->render('workqueue/no-organization.html.twig');
        }

        $params = array_filter($request->query->all());
        $participants = $this->participantSummarySearch($organization, $params, $app);
        $isDVType = !empty($this->getSiteType($app));
        return $app['twig']->render('workqueue/index.html.twig', [
            'filters' => self::$filters,
            'surveys' => self::$surveys,
            'samples' => self::$samples,
            'participants' => $participants,
            'params' => $params,
            'organization' => $organization,
            'isRdrError' => $this->rdrError,
            'isDVType' => $isDVType
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
        if ($request->get('_route') == 'awardee_export') {
            $organization = $app['session']->get('awardeeOrganization');
            $token = $app['security.token_storage']->getToken();
            $user = $token->getUser();
            $awardees = $user->getAwardees();
            $awardeeIds = [];
            foreach ($awardees as $awardee) {
                $awardeeIds[] = $awardee->id;
            }
            $site = implode(',', $awardeeIds);
        } else {
            $organization = $app->getSiteOrganization();
            $site = $app->getSiteId();
        }       
        if (!$organization) {
            return $app['twig']->render('workqueue/no-organization.html.twig');
        }

        $params = array_filter($request->query->all());
        $params['_count'] = self::LIMIT_EXPORT;
        $participants = $this->participantSummarySearch($organization, $params, $app);
        $stream = function() use ($participants) {
            $output = fopen('php://output', 'w');
            // Add UTF-8 BOM
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['This file contains information that is sensitive and confidential. Do not distribute either the file or its contents.']);
            fwrite($output, "\"\"\n");
            $headers = [
                'PMI ID',
                'Biobank ID',
                'Last Name',
                'First Name',
                'Date of Birth',
                'Language',
                'General Consent Status',
                'General Consent Date',
                'EHR Consent Status',
                'EHR Consent Date',
                'CABoR Consent Status',
                'CABoR Consent Date',
                'Withdrawal Status',
                'Withdrawal Date',
                'Street Address',
                'City',
                'State',
                'ZIP',
                'Email',
                'Phone',
                'Sex',
                'Gender Identity',
                'Sexual Orientation',
                'Race/Ethnicity',
                'Education',
                'Income',
                'Required PPI Surveys Complete',
                'Completed Surveys'
            ];
            foreach (self::$surveys as $survey => $label) {
                $headers[] = $label . ' PPI Survey Complete';
                $headers[] = $label . ' PPI Survey Completion Date';
            }
            $headers[] = 'Physical Measurements Status';
            $headers[] = 'Physical Measurements Completion Date';
            $headers[] = 'Samples for DNA Received';
            $headers[] = 'Biospecimens';
            foreach (self::$samples as $sample => $label) {
                $headers[] = $label . ' Collected';
                $headers[] = $label . ' Collection Date';
            }
            fputcsv($output, $headers);
            foreach ($participants as $participant) {
                $row = [
                    $participant->id,
                    $participant->biobankId,
                    $participant->lastName,
                    $participant->firstName,
                    self::csvDateFromObject($participant->dob),
                    $participant->language,
                    self::csvStatusFromSubmitted($participant->consentForStudyEnrollment),
                    self::csvDateFromString($participant->consentForStudyEnrollmentTime),
                    self::csvStatusFromSubmitted($participant->consentForElectronicHealthRecords),
                    self::csvDateFromString($participant->consentForElectronicHealthRecordsTime),
                    self::csvStatusFromSubmitted($participant->consentForconsentForCABoR),
                    self::csvDateFromString($participant->consentForCABoRTime),
                    $participant->withdrawalStatus == 'NO_USE' ? '1' : '0',
                    self::csvDateFromString($participant->withdrawalTime),
                    $participant->streetAddress,
                    $participant->city,
                    $participant->state,
                    $participant->zipCode,
                    $participant->email,
                    $participant->phoneNumber,
                    $participant->sex,
                    $participant->genderIdentity,
                    $participant->sexualOrientation,
                    $participant->race,
                    $participant->education,
                    $participant->income,
                    $participant->numCompletedBaselinePPIModules == 3 ? '1' : '0',
                    $participant->numCompletedPPIModules
                ];
                foreach (self::$surveys as $survey => $label) {
                    $row[] = self::csvStatusFromSubmitted($participant->{"questionnaireOn{$survey}"});
                    $row[] = self::csvDateFromString($participant->{"questionnaireOn{$survey}Time"});
                }
                $row[] = $participant->physicalMeasurementsStatus == 'COMPLETED' ? '1' : '0';
                $row[] = self::csvDateFromString($participant->physicalMeasurementsTime);
                $row[] = $participant->samplesToIsolateDNA == 'RECEIVED' ? '1' : '0';
                $row[] = $participant->numBaselineSamplesArrived;
                foreach (self::$samples as $sample => $label) {
                    $row[] = $participant->{"sampleStatus{$sample}"} == 'RECEIVED' ? '1' : '0';
                    $row[] = self::csvDateFromString($participant->{"sampleStatus{$sample}Time"});
                }
                fputcsv($output, $row);
            }
            fwrite($output, "\"\"\n");
            fputcsv($output, ['Confidential Information']);
            fclose($output);
        };
        $filename = "workqueue_{$organization}_" . date('Ymd-His') . '.csv';

        $app->log(Log::WORKQUEUE_EXPORT, [
            'filter' => $params,
            'site' => $site
        ]);

        return $app->stream($stream, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    public function getSiteType($app) {
        $site = $app['em']->getRepository('sites')->fetchBy([
            'google_group' => $app->getSiteId(),
            'type' => 'DV'
        ]);
        return $site;
    }
}
