<?php
namespace Pmi\WorkQueue;

class WorkQueue
{
    const LIMIT_DEFAULT = 1000;
    const LIMIT_EXPORT = 10000;
    const LIMIT_EXPORT_PAGE_SIZE = 1000;

    public static $wQColumns = [
        'lastName',
        'firstName',
        'dateOfBirth',
        'participantId',
        'biobankId',
        'language',
        'enrollmentStatus',
        'consentForStudyEnrollmentTime',
        'consentForElectronicHealthRecordsTime',
        'consentForCABoRTime',
        'withdrawalTime',
        'recontactMethod',
        'streetAddress',
        'email',
        'phoneNumber',
        'numCompletedBaselinePPIModules',
        'numCompletedPPIModules',
        'questionnaireOnTheBasics',
        'questionnaireOnTheBasicsTime',
        'questionnaireOnOverallHealth',
        'questionnaireOnOverallHealthTime',
        'questionnaireOnLifestyle',
        'questionnaireOnLifestyleTime',
        'questionnaireOnMedicalHistory',
        'questionnaireOnMedicalHistoryTime',
        'questionnaireOnMedications',
        'questionnaireOnMedicationsTime',
        'questionnaireOnFamilyHealth',
        'questionnaireOnFamilyHealthTime',
        'questionnaireOnHealthcareAccess',
        'questionnaireOnHealthcareAccessTime',
        'site',
        'physicalMeasurementsTime',
        'physicalMeasurementsFinalizedSite',
        'samplesToIsolateDNA',
        'numBaselineSamplesArrived',
        'sampleStatus1SST8',
        'sampleStatus1SST8Time',
        'sampleStatus1PST8',
        'sampleStatus1PST8Time',
        'sampleStatus1HEP4',
        'sampleStatus1HEP4Time',
        'sampleStatus1ED04',
        'sampleStatus1ED04Time',
        'sampleStatus1ED10',
        'sampleStatus1ED10Time',
        'sampleStatus2ED10',
        'sampleStatus2ED10Time',
        'sampleStatus1UR10',
        'sampleStatus1UR10Time',
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
        '1ED04' => '4 mL EDTA',
        '1ED10' => '1st 10 mL EDTA',
        '2ED10' => '2nd 10 mL EDTA',
        '1UR10' => 'Urine 10 mL',
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
        ]
    ];

    public static function generateTableRows($participants, $app)
    {
        $rows = [];
        foreach ($participants as $participant) {
            $row = [];
            //Identifiers and status
            if($app->hasRole('ROLE_USER')) {
                $row['lastName'] = '<a href="/participant/'.$participant->id.'">'.$participant->lastName.'</a>';
            } else {
                $row['lastName'] = $participant->lastName;
            }
            if ($app->hasRole('ROLE_USER')) {
                $row['firstName'] = '<a href="/participant/'.$participant->id.'">'.$participant->firstName.'</a>';
            } else {
                $row['firstName'] = $participant->firstName;
            }
            if ($participant->dob) {
                $row['dateOfBirth'] = $participant->dob->format('m/d/Y'); 
            } else {
                $row['dateOfBirth'] = '';
            }
            $row['participantId'] = $participant->id;
            $row['biobankId'] = $participant->biobankId;
            $row['language'] = $participant->language;
            $row['participantStatus'] = $participant->enrollmentStatus;
            if ($participant->consentForStudyEnrollment == 'SUBMITTED') {
                $row['generalConsent'] = '<i class="fa fa-check text-success" aria-hidden="true"></i>'.self::dateFromString($participant->consentForStudyEnrollmentTime, $app->getUserTimezone());         
            }
            else {
                $row['generalConsent'] = '<i class="fa fa-times text-danger" aria-hidden="true"></i>';
            }
            if ($participant->consentForElectronicHealthRecords == 'SUBMITTED') {
                $row['ehrConsent'] = '<i class="fa fa-check text-success" aria-hidden="true"></i>'.self::dateFromString($participant->consentForElectronicHealthRecordsTime, $app->getUserTimezone());
            }
            else {
                $row['ehrConsent'] = '<i class="fa fa-times text-danger" aria-hidden="true"></i>';
            }
            if ($participant->consentForCABoR == 'SUBMITTED') {
                $row['caborConsent'] = '<i class="fa fa-check text-success" aria-hidden="true"></i>'.self::dateFromString($participant->consentForCABoRTime, $app->getUserTimezone());
            }
            else {
                $row['caborConsent'] = '<i class="fa fa-times text-danger" aria-hidden="true"></i>';
            }
            if ($participant->withdrawalStatus == 'NO_USE') {
                $row['withdrawal'] = '<i class="fa fa-times text-danger" aria-hidden="true"></i><span class="text-danger">No Use</span> - '.self::dateFromString($participant->withdrawalTime, $app->getUserTimezone());
            } else {
               $row['withdrawal'] = ''; 
            }

            //Contact
            $row['contactMethod'] = $participant->recontactMethod;
            if ($participant->getAddress) {
                $row['address'] = $participant->getAddress;
            } else {
                $row['address'] = '';  
            }
            $row['email'] = $participant->email;
            $row['phone'] = $participant->phoneNumber;

            //PPI Surveys
            if ($participant->numCompletedBaselinePPIModules == 3) {
                $row['ppiStatus'] = '<i class="fa fa-check text-success" aria-hidden="true"></i>';
            }
            else {
                $row['ppiStatus'] = '<i class="fa fa-times text-danger" aria-hidden="true"></i>';
            }
            $row['ppiSurveys'] = $participant->numCompletedPPIModules;
            foreach (self::$surveys as $field => $survey) {
                if ($participant->{'questionnaireOn'.$field} == 'SUBMITTED') {
                    $row["ppi{$field}"] = '<i class="fa fa-check text-success" aria-hidden="true"></i>';
                }
                else {
                    $row["ppi{$field}"] = '<i class="fa fa-times text-danger" aria-hidden="true"></i>';
                }
                if ($participant->{'questionnaireOn'.$field.'Time'} == 'SUBMITTED') {
                    $row["ppi{$field}Time"] = self::dateFromString($participant->{'questionnaireOn'.$field.'Time'}, $app->getUserTimezone());
                } else {
                    $row["ppi{$field}Time"] = '';
                }
            }

            //In-Person Enrollment
            $row['pairedSiteLocation'] = $participant->siteSuffix;
            if ($participant->physicalMeasurementsStatus == 'COMPLETED') {
                $row['physicalMeasurementsStatus'] = '<i class="fa fa-check text-success" aria-hidden="true"></i>'.self::dateFromString($participant->physicalMeasurementsTime, $app->getUserTimezone());
            }
            else {
                $row['physicalMeasurementsStatus'] = '<i class="fa fa-times text-danger" aria-hidden="true"></i>';
            }
            $row['evaluationFinalizedSite'] = $participant->evaluationFinalizedSite;
            if ($participant->samplesToIsolateDNA == 'RECEIVED') {
                $row['biobankDnaStatus'] = '<i class="fa fa-check text-success" aria-hidden="true"></i>';
            }
            else {
                $row['biobankDnaStatus'] = '<i class="fa fa-times text-danger" aria-hidden="true"></i>';
            }
            if ($participant->numBaselineSamplesArrived >= 7) {
                $row['biobankSamples'] = '<i class="fa fa-check text-success" aria-hidden="true"></i>'.$participant->numBaselineSamplesArrived;
            } else {
                $row['biobankSamples'] = '';
            }
            foreach (array_keys(self::$samples) as $sample) {
                $newSample = $sample;
                if (array_key_exists($sample, self::$samplesAlias[0]) && $participant->{"sampleStatus".self::$samplesAlias[0][$sample].""} == 'RECEIVED') {
                    $newSample = self::$samplesAlias[0][$sample];
                } elseif (array_key_exists($sample, self::$samplesAlias[1]) && $participant->{"sampleStatus".self::$samplesAlias[1][$sample].""} == 'RECEIVED') {
                    $newSample = self::$samplesAlias[1][$sample];
                }
                if ($participant->{'sampleStatus'.$newSample} == 'RECEIVED') {
                    $row["sample{$sample}"] = '<i class="fa fa-check text-success" aria-hidden="true"></i>';
                }
                else {
                    $row["sample{$sample}"] = '<i class="fa fa-times text-danger" aria-hidden="true"></i>';
                }
                if ($participant->{'sampleStatus'.$newSample.'Time'}) {
                    $row["sample{$sample}Time"] = self::dateFromString($participant->{'sampleStatus'.$newSample.'Time'}, $app->getUserTimezone());
                } else {
                    $row["sample{$sample}Time"] = '';
                }
            }
            $row['orderCreatedSite'] = $participant->orderCreatedSite;

            //Demographics
            $row['age'] = $participant->age;
            $row['sex'] = $participant->sex;
            $row['genderIdentity'] = $participant->genderIdentity;
            $row['race'] = $participant->race;
            $row['education'] = $participant->education;
            array_push($rows, $row);
        } 
        return $rows;
    }

    public static function dateFromString($string, $timezone)
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

    public static function csvDateFromObject($date)
    {
        return is_object($date) ? $date->format('m/d/Y') : '';
    }

    public static function csvStatusFromSubmitted($status)
    {
        return $status === 'SUBMITTED' ? 1 : 0;
    }
}
