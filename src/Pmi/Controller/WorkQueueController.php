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
    const LIMIT_EXPORT = 10000;
    const LIMIT_EXPORT_PAGE_SIZE = 1000;

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
                'Refused consent' => 'SUBMITTED_NO_CONSENT',
                'Consent not completed' => 'UNSET'
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
    public static $surveys = [
        'TheBasics' => 'Basics',
        'OverallHealth' => 'Health',
        'Lifestyle' => 'Lifestyle',
        'MedicalHistory' => 'Hist',
        'Medications' => 'Meds',
        'FamilyHealth' => 'Family',
        'HealthcareAccess' => 'Access'
    ];
    public static $samples = [
        '1SST8' => '8 mL SST',
        '1PST8' => '8 mL PST',
        '1HEP4' => '4 mL Na-Hep',
        '1ED04' => '4 mL EDTA',
        '1ED10' => '1st 10 mL EDTA',
        '2ED10' => '2nd 10 mL EDTA',
        '1UR10' => 'Urine 10 mL',
        '1SAL' => 'Saliva'
    ];
    public static $samplesAlias = [
        '1SST8' => '1SS08',
        '1PST8' => '1PS08'
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
            $summaries = $app['pmi.drc.participants']->listParticipantSummaries($rdrParams, true);
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
        if ($app->hasRole('ROLE_USER')) {
            $organization = $app->getSiteOrganization();
        }
        if ($app->hasRole('ROLE_AWARDEE')) {
            $organizations = $app->getAwardeeOrganization();
            if (!empty($organizations)) {
                if (($sessionOrganization = $app['session']->get('awardeeOrganization')) && in_array($sessionOrganization, $organizations)) {
                    $organization = $sessionOrganization;
                } else {
                    // Default to first organization
                    $organization = $organizations[0];
                }
            }
        }
        if (empty($organization)) {
            return $app['twig']->render('workqueue/no-organization.html.twig');
        }

        $params = array_filter($request->query->all());
        $filters = self::$filters;

        //Add sites filter
        $sites = $app->getSitesFromOrganization($organization);
        if (!empty($sites)) {
            $sitesList = [];
            $sitesList['site']['label'] = 'Paired Site Location';
            foreach ($sites as $key => $site) {
                $sitesList['site']['options'][$site['google_group']] = \Pmi\Security\User::SITE_PREFIX.$site['google_group'];
            }
            $filters = array_merge($filters, $sitesList);
        }

        if ($app->hasRole('ROLE_AWARDEE')) {
            // Add organizations to filters
            $organizationsList = [];
            $organizationsList['organization']['label'] = 'Organization';
            foreach ($organizations as $org) {
                $organizationsList['organization']['options'][$org] = $org;
            }
            $filters = array_merge($filters, $organizationsList);

            // Set to selected organization
            if (isset($params['organization'])) {
                // Check if the awardee has access to this organization
                if (!in_array($params['organization'], $app->getAwardeeOrganization())) {
                    $app->abort(403);
                }
                $organization = $params['organization'];
                unset($params['organization']);
            }
            // Save selected (or default) organization in session
            $app['session']->set('awardeeOrganization', $organization);
        }
        $participants = $this->participantSummarySearch($organization, $params, $app);
        $siteWorkQueueDownload = $this->getSiteWorkQueueDownload($app);
        return $app['twig']->render('workqueue/index.html.twig', [
            'filters' => $filters,
            'surveys' => self::$surveys,
            'samples' => self::$samples,
            'participants' => $participants,
            'params' => $params,
            'organization' => $organization,
            'isRdrError' => $this->rdrError,
            'samplesAlias' => self::$samplesAlias,
            'isDownloadDisabled' => $this->isDownloadDisabled($siteWorkQueueDownload)
        ]);
    }

    protected static function csvDateFromObject($date)
    {
        return is_object($date) ? $date->format('m/d/Y') : '';
    }

    protected static function csvDateFromString($string, $timezone)
    {
        if (!empty($string)) {
            try {
                $date = new \DateTime($string);
                $date->setTimezone(new \DateTimeZone($timezone));
                return $date->format('m/d/Y');
            } catch (\Exception $e) {
                return '';
            }
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
        $siteWorkQueueDownload = $this->getSiteWorkQueueDownload($app);
        if ($siteWorkQueueDownload === AdminController::DOWNLOAD_DISABLED) {
            $app->abort(403);
        }
        if ($app->hasRole('ROLE_AWARDEE')) {
            $organization = $app['session']->get('awardeeOrganization');
            $site = $app->getAwardeeId();
        } else {
            $organization = $app->getSiteOrganization();
            $site = $app->getSiteId();
        }       
        if (!$organization) {
            return $app['twig']->render('workqueue/no-organization.html.twig');
        }

        $hasFullDataAcess = $siteWorkQueueDownload === AdminController::FULL_DATA_ACCESS || $app->hasRole('ROLE_AWARDEE');

        $params = array_filter($request->query->all());
        $params['_count'] = self::LIMIT_EXPORT_PAGE_SIZE;

        $stream = function() use ($app, $params, $organization, $hasFullDataAcess) {
            $output = fopen('php://output', 'w');
            // Add UTF-8 BOM
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['This file contains information that is sensitive and confidential. Do not distribute either the file or its contents.']);
            fwrite($output, "\"\"\n");
            if ($hasFullDataAcess) {
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
                    'Race/Ethnicity',
                    'Education',
                    'Required PPI Surveys Complete',
                    'Completed Surveys'
                ];
                foreach (self::$surveys as $survey => $label) {
                    $headers[] = $label . ' PPI Survey Complete';
                    $headers[] = $label . ' PPI Survey Completion Date';
                }
            } else {
                $headers = [
                    'PMI ID',
                    'Biobank ID',
                    'ZIP'
                ];                
            }
            $headers[] = 'Physical Measurements Status';
            $headers[] = 'Physical Measurements Completion Date';
            $headers[] = 'Paired Site Location';
            $headers[] = 'Physical Measurements Location';
            $headers[] = 'Samples for DNA Received';
            $headers[] = 'Biospecimens';
            foreach (self::$samples as $sample => $label) {
                $headers[] = $label . ' Collected';
                $headers[] = $label . ' Collection Date';
            }
            $headers[] = 'Biospecimens Location';
            fputcsv($output, $headers);

            for ($i = 0; $i < ceil(self::LIMIT_EXPORT / self::LIMIT_EXPORT_PAGE_SIZE); $i++) {
                $participants = $this->participantSummarySearch($organization, $params, $app);
                foreach ($participants as $participant) {
                    if ($hasFullDataAcess) {
                        $row = [
                            $participant->id,
                            $participant->biobankId,
                            $participant->lastName,
                            $participant->firstName,
                            self::csvDateFromObject($participant->dob),
                            $participant->language,
                            self::csvStatusFromSubmitted($participant->consentForStudyEnrollment),
                            self::csvDateFromString($participant->consentForStudyEnrollmentTime, $app->getUserTimezone()),
                            self::csvStatusFromSubmitted($participant->consentForElectronicHealthRecords),
                            self::csvDateFromString($participant->consentForElectronicHealthRecordsTime, $app->getUserTimezone()),
                            self::csvStatusFromSubmitted($participant->consentForconsentForCABoR),
                            self::csvDateFromString($participant->consentForCABoRTime, $app->getUserTimezone()),
                            $participant->withdrawalStatus == 'NO_USE' ? '1' : '0',
                            self::csvDateFromString($participant->withdrawalTime, $app->getUserTimezone()),
                            $participant->streetAddress,
                            $participant->city,
                            $participant->state,
                            $participant->zipCode,
                            $participant->email,
                            $participant->phoneNumber,
                            $participant->sex,
                            $participant->genderIdentity,
                            $participant->race,
                            $participant->education,
                            $participant->numCompletedBaselinePPIModules == 3 ? '1' : '0',
                            $participant->numCompletedPPIModules
                        ];
                        foreach (self::$surveys as $survey => $label) {
                            $row[] = self::csvStatusFromSubmitted($participant->{"questionnaireOn{$survey}"});
                            $row[] = self::csvDateFromString($participant->{"questionnaireOn{$survey}Time"}, $app->getUserTimezone());
                        }
                    } else {
                        $row = [
                            $participant->id,
                            $participant->biobankId,
                            $participant->zipCode,
                        ];                   
                    }
                    $row[] = $participant->physicalMeasurementsStatus == 'COMPLETED' ? '1' : '0';
                    $row[] = self::csvDateFromString($participant->physicalMeasurementsTime, $app->getUserTimezone());
                    $row[] = $participant->site;
                    $row[] = $participant->evaluationFinalizedSite;
                    $row[] = $participant->samplesToIsolateDNA == 'RECEIVED' ? '1' : '0';
                    $row[] = $participant->numBaselineSamplesArrived;
                    foreach (self::$samples as $sample => $label) {
                        if (array_key_exists($sample, self::$samplesAlias)) {
                            $sampleAlias = self::$samplesAlias[$sample];
                            if ($participant->{"sampleStatus{$sampleAlias}"} == 'RECEIVED') {
                                $sample = $sampleAlias;
                            }
                        }
                        $row[] = $participant->{"sampleStatus{$sample}"} == 'RECEIVED' ? '1' : '0';
                        $row[] = self::csvDateFromString($participant->{"sampleStatus{$sample}Time"}, $app->getUserTimezone());
                    }
                    $row[] = $participant->orderCreatedSite;
                    fputcsv($output, $row);
                }
                unset($participants);
                if (!$app['pmi.drc.participants']->getNextToken()) {
                    break;
                }
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

    public function getSiteWorkQueueDownload($app)
    {
        $site = $app['em']->getRepository('sites')->fetchOneBy([
            'google_group' => $app->getSiteId()
        ]);
        return !empty($site) ? $site['workqueue_download'] : null;
    }

    public function isDownloadDisabled($value)
    {
        return $value === AdminController::DOWNLOAD_DISABLED;
    }
}
