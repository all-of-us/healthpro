<?php
namespace Pmi\WorkQueue;

class WorkQueue
{
    const LIMIT_EXPORT = 10000;
    const LIMIT_EXPORT_PAGE_SIZE = 1000;

    const HTML_SUCCESS = '<i class="fa fa-check text-success" aria-hidden="true"></i>';
    const HTML_DANGER = '<i class="fa fa-times text-danger" aria-hidden="true"></i>';
    const HTML_WARNING = '<i class="fa fa-question text-warning" aria-hidden="true"></i>';
    const HTML_NOTICE = '<i class="fa fa-stop-circle text-warning" aria-hidden="true"></i>';

    protected $app;

    // These are used to map a DataTables column index to an RDR field for sorting
    public static $wQColumns = [
        'lastName',
        'firstName',
        'middleName',
        'dateOfBirth',
        'participantId',
        'biobankId',
        'language',
        'enrollmentStatus',
        'participantOrigin',
        'consentForStudyEnrollmentAuthored',
        'primaryLanguage',
        'consentForElectronicHealthRecordsAuthored',
        'consentForDvElectronicHealthRecordsSharingAuthored',
        'consentForCABoRAuthored',
        'withdrawalAuthored',
        'withdrawalReason',
        'patientStatus',
        'patientStatus',
        'patientStatus',
        'patientStatus',
        'recontactMethod',
        'streetAddress',
        'email',
        'loginPhoneNumber',
        'phoneNumber',
        'numCompletedBaselinePPIModules',
        'numCompletedPPIModules',
        'questionnaireOnTheBasics',
        'questionnaireOnTheBasicsAuthored',
        'questionnaireOnOverallHealth',
        'questionnaireOnOverallHealthAuthored',
        'questionnaireOnLifestyle',
        'questionnaireOnLifestyleAuthored',
        'questionnaireOnMedicalHistory',
        'questionnaireOnMedicalHistoryAuthored',
        'questionnaireOnMedications',
        'questionnaireOnMedicationsAuthored',
        'questionnaireOnFamilyHealth',
        'questionnaireOnFamilyHealthAuthored',
        'questionnaireOnHealthcareAccess',
        'questionnaireOnHealthcareAccessAuthored',
        'site',
        'organization',
        'physicalMeasurementsFinalizedTime',
        'physicalMeasurementsFinalizedSite',
        'samplesToIsolateDNA',
        'numBaselineSamplesArrived',
        'sampleStatus1SST8',
        'sampleStatus1SST8Time',
        'sampleStatus1PST8',
        'sampleStatus1PST8Time',
        'sampleStatus1HEP4',
        'sampleStatus1HEP4Time',
        'sampleStatus1ED02',
        'sampleStatus1ED02Time',
        'sampleStatus1ED04',
        'sampleStatus1ED04Time',
        'sampleStatus1ED10',
        'sampleStatus1ED10Time',
        'sampleStatus2ED10',
        'sampleStatus2ED10Time',
        'sampleStatus1CFD9',
        'sampleStatus1CFD9Time',
        'sampleStatus1PXR2',
        'sampleStatus1PXR2Time',
        'sampleStatus1UR10',
        'sampleStatus1UR10Time',
        'sampleStatus1UR90',
        'sampleStatus1UR90Time',
        'sampleStatus1SAL',
        'sampleStatus1SALTime',
        'biospecimenSourceSite',
        'dateOfBirth',
        'sex',
        'genderIdentity',
        'race',
        'education',
    ];

    public static $filters = [
        'activityStatus' => [
            'label' => 'Activity Status',
            'options' => [
                'Active' => 'active',
                'Deactivated' => 'deactivated',
                'Withdrawn' => 'withdrawn',
                'Not Withdrawn' => 'not_withdrawn'
            ]
        ],
        'enrollmentStatus' => [
            'label' => 'Participant Status',
            'options' => [
                'Participant' => 'INTERESTED',
                'Participant + EHR Consent' => 'MEMBER',
                'Core Participant' => 'FULL_PARTICIPANT'
            ]
        ],
        'patientStatus' => [
            'label' => 'Patient Status',
            'options' => [
                'Yes' => 'YES',
                'No' => 'NO',
                'No Access' => 'NO_ACCESS',
                'Unknown' => 'UNKNOWN',
                'Not Completed' => 'UNSET'
            ]
        ],
        'consentForElectronicHealthRecords' => [
            'label' => 'EHR Consent Status',
            'options' => [
                'Consented' => 'SUBMITTED',
                'Refused consent' => 'SUBMITTED_NO_CONSENT',
                'Consent not completed' => 'UNSET',
                'Invalid' => 'SUBMITTED_INVALID'
            ]
        ],
        'consentForGenomicsROR' => [
            'label' => 'gRoR Consent Status',
            'options' => [
                'Consented' => 'SUBMITTED',
                'Refused consent' => 'SUBMITTED_NO_CONSENT',
                'Refused not sure' => 'SUBMITTED_NOT_SURE',
                'Consent not completed' => 'UNSET',
                'Invalid' => 'SUBMITTED_INVALID'
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
                'More Than One Gender Identity' => 'GenderIdentity_MoreThanOne',
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
        ],
        'participantOrigin' => [
            'label' => 'Participant Origination',
            'options' => [
                'PTSC Portal' => 'vibrent',
                'DV Pilot Portal' => 'careevolution'
            ]
        ]
    ];

    // These are currently not working in the RDR
    public static $filtersDisabled = [
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
        '1ED02' => '2 mL EDTA',
        '1ED04' => '4 mL EDTA',
        '1ED10' => '1st 10 mL EDTA',
        '2ED10' => '2nd 10 mL EDTA',
        '1CFD9' => 'Cell-Free DNA',
        '1PXR2' => 'Paxgene RNA',
        '1UR10' => 'Urine 10 mL',
        '1UR90' => 'Urine 90 mL',
        '1SAL' => 'Saliva'
    ];

    public static $samplesAlias = [
        [
            '1SST8' => '1SS08',
            '1PST8' => '1PS08'
        ],
        [
            '1SST8' => '2SST8',
            '1PST8' => '2PST8'
        ],
        [
            '1SAL' => '1SAL2'
        ]
    ];

    public function generateTableRows($participants, $app)
    {
        $e = function($string) {
            return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $this->app = $app;
        $rows = [];
        foreach ($participants as $participant) {
            $row = [];
            //Identifiers and status
            if ($this->app->hasRole('ROLE_USER') || $this->app->hasRole('ROLE_AWARDEE_SCRIPPS')) {
                $row['lastName'] = $this->generateLink($participant->id, $participant->lastName);
                $row['middleName'] = $this->generateLink($participant->id, $participant->middleName);
                $row['firstName'] = $this->generateLink($participant->id, $participant->firstName);
            } else {
                $row['lastName'] = $e($participant->lastName);
                $row['firstName'] = $e($participant->firstName);
                $row['middleName'] = $e($participant->middleName);
            }
            if (!empty($participant->dob)) {
                $row['dateOfBirth'] = $participant->dob->format('m/d/Y');
            } else {
                $row['dateOfBirth'] = '';
            }
            $row['patientStatusYes'] = $this->getPatientStatus($participant, 'YES');
            $row['patientStatusNo'] = $this->getPatientStatus($participant, 'NO');
            $row['patientStatusUnknown'] = $this->getPatientStatus($participant, 'NO_ACCESS');
            $row['patientStatusNoAccess'] = $this->getPatientStatus($participant, 'UNKNOWN');
            $row['participantId'] = $e($participant->id);
            $row['biobankId'] = $e($participant->biobankId);
            $row['language'] = $e($participant->language);
            $row['participantOrigin'] = $e($participant->participantOrigin);
            $enrollmentStatusCoreSampleTime = $participant->isCoreParticipant ? '<br/>' . self::dateFromString($participant->enrollmentStatusCoreStoredSampleTime, $app->getUserTimezone()) : '';
            $row['participantStatus'] = $e($participant->enrollmentStatus) . $enrollmentStatusCoreSampleTime;
            $row['generalConsent'] = $this->displayConsentStatus($participant->consentForStudyEnrollment, $participant->consentForStudyEnrollmentAuthored);
            $row['primaryLanguage'] = $e($participant->primaryLanguage);
            $row['ehrConsent'] = $this->displayConsentStatus($participant->consentForElectronicHealthRecords, $participant->consentForElectronicHealthRecordsAuthored);
            $row['gRoRConsent'] = $this->displayConsentStatus($participant->consentForGenomicsROR, $participant->consentForGenomicsRORAuthored);
            $row['dvEhrStatus'] = $this->displayConsentStatus($participant->consentForDvElectronicHealthRecordsSharing, $participant->consentForDvElectronicHealthRecordsSharingAuthored);
            $row['caborConsent'] = $this->displayConsentStatus($participant->consentForCABoR, $participant->consentForCABoRAuthored);
            $row['activityStatus'] = $this->getActivityStatus($participant);
            $row['withdrawalStatus'] = $participant->withdrawalStatus; // Used to add withdrawn class in the data tables
            $row['withdrawalReason'] = $e($participant->withdrawalReason);

            //Contact
            $row['contactMethod'] = $e($participant->recontactMethod);
            if ($participant->getAddress()) {
                $row['address'] = $e($participant->getAddress());
            } else {
                $row['address'] = '';
            }
            $row['email'] = $e($participant->email);
            $row['loginPhone'] = $e($participant->loginPhoneNumber);
            $row['phone'] = $e($participant->phoneNumber);

            //PPI Surveys
            if ($participant->numCompletedBaselinePPIModules == 3) {
                $row['ppiStatus'] = self::HTML_SUCCESS;
            }
            else {
                $row['ppiStatus'] = self::HTML_DANGER;
            }
            $row['ppiSurveys'] = $e($participant->numCompletedPPIModules);
            foreach (array_keys(self::$surveys) as $field) {
                $row["ppi{$field}"] = $this->displayStatus($participant->{'questionnaireOn' . $field}, 'SUBMITTED');
                $row["ppi{$field}Time"] = self::dateFromString($participant->{'questionnaireOn' . $field . 'Authored'}, $app->getUserTimezone());
            }

            //In-Person Enrollment
            $row['pairedSite'] = $this->app->getSiteDisplayName($e($participant->siteSuffix));
            $row['pairedOrganization'] = $this->app->getOrganizationDisplayName($e($participant->organization));
            $row['physicalMeasurementsStatus'] = $this->displayStatus($participant->physicalMeasurementsStatus, 'COMPLETED', $participant->physicalMeasurementsFinalizedTime, false);
            $row['evaluationFinalizedSite'] = $this->app->getSiteDisplayName($e($participant->evaluationFinalizedSite));
            $row['biobankDnaStatus'] = $this->displayStatus($participant->samplesToIsolateDNA, 'RECEIVED');
            if ($participant->numBaselineSamplesArrived >= 7) {
                $row['biobankSamples'] = self::HTML_SUCCESS . ' ' . $e($participant->numBaselineSamplesArrived);
            } else {
                $row['biobankSamples'] = $e($participant->numBaselineSamplesArrived);;
            }
            foreach (array_keys(self::$samples) as $sample) {
                $newSample = $sample;
                foreach (self::$samplesAlias as $sampleAlias) {
                    if (array_key_exists($sample, $sampleAlias) && $participant->{"sampleStatus" . $sampleAlias[$sample]} == 'RECEIVED') {
                        $newSample = $sampleAlias[$sample];
                        break;
                    }
                }
                $row["sample{$sample}"] = $this->displayStatus($participant->{'sampleStatus' . $newSample}, 'RECEIVED');
                $row["sample{$sample}Time"] = self::dateFromString($participant->{'sampleStatus' . $newSample . 'Time'}, $app->getUserTimezone(), false);
            }
            $row['orderCreatedSite'] = $this->app->getSiteDisplayName($e($participant->orderCreatedSite));

            //Demographics
            $row['age'] = $e($participant->age);
            $row['sex'] = $e($participant->sex);
            $row['genderIdentity'] = $e($participant->genderIdentity);
            $row['race'] = $e($participant->race);
            $row['education'] = $e($participant->education);
            array_push($rows, $row);
        }
        return $rows;
    }

    public static function dateFromString($string, $timezone, $displayTime = true)
    {
        if (!empty($string)) {
            try {
                $date = new \DateTime($string);
                $date->setTimezone(new \DateTimeZone($timezone));
                if ($displayTime) {
                    return $date->format('n/j/Y g:i a');
                }
                return $date->format('n/j/Y');
            } catch (\Exception $e) {
                return '';
            }
        } else {
            return '';
        }
    }

    public static function csvDateFromObject($date)
    {
        return is_object($date) ? $date->format('m/d/Y') : '';
    }

    public static function csvStatusFromSubmitted($status)
    {
        switch ($status) {
            case 'SUBMITTED':
                return 1;
            case 'SUBMITTED_NOT_SURE':
                return 2;
            default:
                return 0;
        }
    }

    public function displayStatus($value, $successStatus, $time = null, $displayTime = true)
    {
        if ($value === $successStatus) {
            return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $this->app->getUserTimezone(), $displayTime);
        } elseif ($value === "{$successStatus}_NOT_SURE") {
            return self::HTML_WARNING . ' ' . self::dateFromString($time, $this->app->getUserTimezone(), $displayTime);
        }
        return self::HTML_DANGER;
    }

    public function displayConsentStatus($value, $time, $displayTime = true)
    {
        switch ($value) {
            case 'SUBMITTED':
                return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $this->app->getUserTimezone(), $displayTime) . ' (consented yes)';
            case 'SUBMITTED_NO_CONSENT':
                return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $this->app->getUserTimezone(), $displayTime) . ' (refused consent)';
            case 'SUBMITTED_NO_SURE':
                return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $this->app->getUserTimezone(), $displayTime) . ' (not sure)';
            case 'SUBMITTED_INVALID':
                return self::HTML_DANGER . ' ' . self::dateFromString($time, $this->app->getUserTimezone(), $displayTime) . ' (invalid)';
            default:
                return self::HTML_DANGER . ' (consent not completed)';
        }
    }

    public function generateLink($id, $name)
    {
        if($this->app->hasRole('ROLE_USER')) {
            $url = $this->app['url_generator']->generate('participant', ['id' => $id]);
        } else {
            $url = $this->app['url_generator']->generate('workqueue_participant', ['id' => $id]);
        }
        $text = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf('<a href="%s">%s</a>', $url, $text);
    }

    public function getPatientStatus($participant, $value, $type = 'wq')
    {
        // Clear patient status for withdrawn participants
        if ($participant->withdrawalStatus === 'NO_USE') {
            return '';
        }
        $organizations = [];
        foreach ($participant->patientStatus as $patientStatus) {
            if ($patientStatus->status === $value) {
                if ($type === 'export') {
                    $organizations[] = $patientStatus->organization;
                } else {
                    $organizations[] = $this->app->getOrganizationDisplayName($patientStatus->organization);
                }
            }
        }
        return implode('; ', $organizations);
    }

    public function getActivityStatus($participant)
    {
        switch ($participant->activityStatus) {
            case 'withdrawn':
                return self::HTML_DANGER . '<span class="text-danger"> Withdrawn </span>' . self::dateFromString($participant->withdrawalAuthored, $this->app->getUserTimezone());
            case 'active':
                return self::HTML_SUCCESS . ' Active';
            case 'deactivated':
                return self::HTML_NOTICE . ' Deactivated ' . self::dateFromString($participant->suspensionTime, $this->app->getUserTimezone());
            default:
                return '';
        }
    }
}
