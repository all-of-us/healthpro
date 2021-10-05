<?php

namespace App\Helper;

class WorkQueue
{
    public const LIMIT_EXPORT = 10000;
    public const LIMIT_EXPORT_PAGE_SIZE = 1000;
    public const FULL_DATA_ACCESS = 'full_data';
    public const LIMITED_DATA_ACCESS = 'limited_data';
    public const DOWNLOAD_DISABLED = 'disabled';

    public const HTML_SUCCESS = '<i class="fa fa-check text-success" aria-hidden="true"></i>';
    public const HTML_DANGER = '<i class="fa fa-times text-danger" aria-hidden="true"></i>';
    public const HTML_WARNING = '<i class="fa fa-question text-warning" aria-hidden="true"></i>';
    public const HTML_NOTICE = '<i class="fa fa-stop-circle text-warning" aria-hidden="true"></i>';

    public static $columnsDef = [
        'lastName' => [
            'displayName' => 'Last Name',
            'csvLabel' => 'Last Name',
            'rdrField' => 'lastName',
            'sortField' => 'lastName',
            'generateLink' => true,
            'toggleColumn' => false
        ],
        'firstName' => [
            'displayName' => 'First Name',
            'csvLabel' => 'First Name',
            'rdrField' => 'firstName',
            'sortField' => 'firstName',
            'generateLink' => true,
            'toggleColumn' => false
        ],
        'middleName' => [
            'displayName' => 'Middle Name',
            'csvLabel' => 'Middle Name',
            'rdrField' => 'middleName',
            'sortField' => 'firstName',
            'generateLink' => true,
            'toggleColumn' => false
        ],
        'dateOfBirth' => [
            'displayName' => 'Date of Birth',
            'csvLabel' => 'Date of Birth',
            'rdrField' => 'dob',
            'sortField' => 'dateOfBirth',
            'formatDate' => true,
            'csvMethod' => 'csvDateFromObject',
            'toggleColumn' => false
        ],
        'participantId' => [
            'displayName' => 'PM ID',
            'csvLabel' => 'PM ID',
            'rdrField' => 'id',
            'sortField' => 'participantId',
            'toggleColumn' => false
        ],
        'primaryConsent' => [
            'displayName' => 'Primary Consent',
            'csvLabels' => [
                'Primary Consent Status',
                'Primary Consent Status Date'
            ],
            'rdrField' => 'consentForStudyEnrollment',
            'sortField' => 'consentForStudyEnrollmentAuthored',
            'rdrDateField' => 'consentForStudyEnrollmentAuthored',
            'method' => 'displayConsentStatus',
            'htmlClass' => 'text-center',
            'toggleColumn' => true
        ],
        'questionnaireOnDnaProgram' => [
            'displayName' => 'Program Update',
            'csvLabels' => [
                'Program Update',
                'Date of Program Update'
            ],
            'rdrField' => 'questionnaireOnDnaProgram',
            'sortField' => 'questionnaireOnDnaProgramAuthored',
            'rdrDateField' => 'questionnaireOnDnaProgramAuthored',
            'otherField' => 'consentCohort',
            'method' => 'displayProgramUpdate',
            'htmlClass' => 'text-center',
            'toggleColumn' => true
        ],
        'ehrConsent' => [
            'displayName' => 'EHR Consent',
            'csvLabels' => [
                'EHR Consent Status',
                'EHR Consent Status Date'
            ],
            'rdrField' => 'consentForElectronicHealthRecords',
            'sortField' => 'consentForElectronicHealthRecordsAuthored',
            'rdrDateField' => 'consentForElectronicHealthRecordsAuthored',
            'method' => 'displayConsentStatus',
            'htmlClass' => 'text-center',
            'toggleColumn' => true
        ],
        'ehrConsentExpireStatus' => [
            'displayName' => 'EHR Expiration Status',
            'csvLabels' => [
                'EHR Expiration Status',
                'EHR Expiration Date'
            ],
            'rdrField' => 'ehrConsentExpireStatus',
            'sortField' => 'ehrConsentExpireStatus',
            'rdrDateField' => 'ehrConsentExpireAuthored',
            'otherField' => 'consentForElectronicHealthRecords',
            'method' => 'displayEhrConsentExpireStatus',
            'csvMethod' => 'csvEhrConsentExpireStatus',
            'htmlClass' => 'text-center',
            'toggleColumn' => true
        ],
        'gRoRConsent' => [
            'displayName' => 'gRoR Consent',
            'csvLabels' => [
                'gRoR Consent',
                'gRoR Consent Date'
            ],
            'rdrField' => 'consentForGenomicsROR',
            'sortField' => 'consentForGenomicsRORAuthored',
            'rdrDateField' => 'consentForGenomicsRORAuthored',
            'method' => 'displayGenomicsConsentStatus',
            'htmlClass' => 'text-center',
            'toggleColumn' => true
        ],
        'dvEhrStatus' => [
            'displayName' => 'DV-only EHR Sharing',
            'csvLabels' => [
                'DV-only EHR Sharing',
                'DV-only EHR Sharing Date'
            ],
            'rdrField' => 'consentForDvElectronicHealthRecordsSharing',
            'sortField' => 'consentForDvElectronicHealthRecordsSharingAuthored',
            'rdrDateField' => 'consentForDvElectronicHealthRecordsSharingAuthored',
            'method' => 'displayConsentStatus',
            'htmlClass' => 'text-center',
            'toggleColumn' => true
        ],
        'caborConsent' => [
            'displayName' => 'CABoR Consent',
            'csvLabels' => [
                'CABoR Consent',
                'CABoR Consent Date'
            ],
            'rdrField' => 'consentForCABoR',
            'sortField' => 'consentForCABoRAuthored',
            'rdrDateField' => 'consentForCABoRAuthored',
            'method' => 'displayConsentStatus',
            'htmlClass' => 'text-center',
            'toggleColumn' => true
        ],
        'digitalHealthSharingStatus' => [
            'displayNames' => [
                'fitbit' => 'Fitbit Consent',
                'appleHealthKit' => 'Apple HealthKit Consent',
                'appleEHR' => 'Apple EHR Consent'
            ],
            'csvLabels' => [
                'Fitbit Consent',
                'Fitbit Consent Date',
                'Apple HealthKit  Consent',
                'Apple HealthKit  Consent Date',
                'Apple EHR Consent',
                'Apple EHR Consent Date'
            ],
            'rdrField' => 'digitalHealthSharingStatus',
            'method' => 'getDigitalHealthSharingStatus',
            'csvMethod' => 'csvDigitalHealthSharingStatus',
            'htmlClass' => 'text-center',
            'orderable' => false,
            'toggleColumn' => true
        ],
        'consentCohort' => [
            'displayName' => 'Consent Cohort',
            'csvLabel' => 'Consent Cohort',
            'rdrField' => 'consentCohortText',
            'sortField' => 'consentCohort',
            'htmlClass' => 'text-center',
            'toggleColumn' => true
        ],
        'primaryLanguage' => [
            'displayName' => 'Language of Primary Consent',
            'csvLabel' => 'Language of Primary Consent',
            'rdrField' => 'primaryLanguage',
            'sortField' => 'primaryLanguage',
            'toggleColumn' => true
        ]
    ];

    public static $sortColumns = [
        'lastName',
        'firstName',
        'middleName',
        'dateOfBirth',
        'participantId',
        'biobankId',
        'enrollmentStatus',
        'withdrawalAuthored',
        'withdrawalReason',
        'participantOrigin',
        'consentCohort',
        'consentForStudyEnrollmentFirstYesAuthored',
        'consentForStudyEnrollmentAuthored',
        'questionnaireOnDnaProgramAuthored',
        'consentForElectronicHealthRecordsFirstYesAuthored',
        'consentForElectronicHealthRecordsAuthored',
        'ehrConsentExpireStatus',
        'consentForGenomicsRORAuthored',
        'primaryLanguage',
        'consentForDvElectronicHealthRecordsSharingAuthored',
        'consentForCABoRAuthored',
        'digitalHealthSharingStatus',
        'digitalHealthSharingStatus',
        'digitalHealthSharingStatus',
        'retentionEligibleTime',
        'retentionType',
        'isEhrDataAvailable',
        'latestEhrReceiptTime',
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
        'questionnaireOnTheBasicsAuthored',
        'questionnaireOnOverallHealthAuthored',
        'questionnaireOnLifestyleAuthored',
        'questionnaireOnMedicalHistoryAuthored',
        'questionnaireOnFamilyHealthAuthored',
        'questionnaireOnHealthcareAccessAuthored',
        'questionnaireOnCopeMayAuthored',
        'questionnaireOnCopeJuneAuthored',
        'questionnaireOnCopeJulyAuthored',
        'questionnaireOnCopeNovAuthored',
        'questionnaireOnCopeDecAuthored',
        'questionnaireOnCopeFebAuthored',
        'questionnaireOnCopeVaccineMinute1',
        'questionnaireOnCopeVaccineMinute2',
        'site',
        'organization',
        'physicalMeasurementsFinalizedTime',
        'physicalMeasurementsFinalizedSite',
        'samplesToIsolateDNA',
        'numBaselineSamplesArrived',
        'biospecimenSourceSite',
        'sampleStatus1SST8Time',
        'sampleStatus1PST8Time',
        'sampleStatus1HEP4Time',
        'sampleStatus1ED02Time',
        'sampleStatus1ED04Time',
        'sampleStatus1ED10Time',
        'sampleStatus2ED10Time',
        'sampleStatus1CFD9Time',
        'sampleStatus1PXR2Time',
        'sampleStatus1UR10Time',
        'sampleStatus1UR90Time',
        'sampleStatus1SALTime',
        'dateOfBirth',
        'sex',
        'genderIdentity',
        'race',
        'education',
    ];

    public static $consentSortColumns = [
        'lastName',
        'firstName',
        'middleName',
        'dateOfBirth',
        'participantId',
        'consentForStudyEnrollmentAuthored',
        'questionnaireOnDnaProgramAuthored',
        'consentForElectronicHealthRecordsAuthored',
        'ehrConsentExpireStatus',
        'consentForGenomicsRORAuthored',
        'consentForDvElectronicHealthRecordsSharingAuthored',
        'consentForCABoRAuthored',
        'digitalHealthSharingStatus',
        'digitalHealthSharingStatus',
        'digitalHealthSharingStatus',
        'consentCohort',
        'primaryLanguage'
    ];

    public static $filters = [
        'activityStatus' => [
            'label' => 'Activity Status',
            'options' => [
                'Active' => 'active',
                'Deactivated' => 'deactivated',
                'Withdrawn' => 'withdrawn',
                'Not Withdrawn' => 'not_withdrawn',
                'Deceased' => 'deceased',
                'Deceased (Pending)' => 'deceased_pending'
            ]
        ],
        'enrollmentStatus' => [
            'label' => 'Participant Status',
            'options' => [
                'Participant' => 'INTERESTED',
                'Participant + EHR Consent' => 'MEMBER',
                'Core Participant' => 'FULL_PARTICIPANT',
                'Core Participant Minus PM' => 'CORE_MINUS_PM'
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
                'Consent not completed' => 'UNSET'
            ]
        ],
        'consentForGenomicsROR' => [
            'label' => 'gRoR Consent Status',
            'options' => [
                'Consented Yes' => 'SUBMITTED',
                'Refused Consent' => 'SUBMITTED_NO_CONSENT',
                'Responded Not Sure' => 'SUBMITTED_NOT_SURE',
                'Consent Not Completed' => 'UNSET'
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
        ],
        'consentCohort' => [
            'label' => 'Consent Cohort',
            'options' => [
                'Cohort 1' => 'COHORT_1',
                'Cohort 2' => 'COHORT_2',
                'Cohort 2 Pilot' => 'COHORT_2_PILOT',
                'Cohort 3' => 'COHORT_3'
            ]
        ],
        'ehrConsentExpireStatus' => [
            'label' => 'EHR Expiration Status',
            'options' => [
                'Active' => 'ACTIVE',
                'Expired' => 'EXPIRED'
            ]
        ],
        'retentionEligibleStatus' => [
            'label' => 'Retention Eligible',
            'options' => [
                'Yes' => 'ELIGIBLE',
                'No' => 'NOT_ELIGIBLE'
            ]
        ],
        'retentionType' => [
            'label' => 'Retention Status',
            'options' => [
                'Active Only' => 'ACTIVE',
                'Passive Only' => 'PASSIVE',
                'Active and Passive' => 'ACTIVE_AND_PASSIVE',
                'Not Retained' => 'UNSET'
            ]
        ],
        'isEhrDataAvailable' => [
            'label' => 'EHR Data Transfer',
            'options' => [
                'Yes' => 'yes',
                'No' => 'no'
            ]
        ]
    ];

    public static $consentFilters = [
        'activityStatus' => [
            'label' => 'Activity Status',
            'options' => [
                'Active' => 'active',
                'Deactivated' => 'deactivated',
                'Withdrawn' => 'withdrawn',
                'Not Withdrawn' => 'not_withdrawn',
                'Deceased' => 'deceased',
                'Deceased (Pending)' => 'deceased_pending'
            ]
        ],
        'enrollmentStatus' => [
            'label' => 'Participant Status',
            'options' => [
                'Participant' => 'INTERESTED',
                'Participant + EHR Consent' => 'MEMBER',
                'Core Participant' => 'FULL_PARTICIPANT',
                'Core Participant Minus PM' => 'CORE_MINUS_PM'
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
        ],
        'retentionEligibleStatus' => [
            'label' => 'Retention Eligible',
            'options' => [
                'Yes' => 'ELIGIBLE',
                'No' => 'NOT_ELIGIBLE'
            ]
        ],
        'retentionType' => [
            'label' => 'Retention Status',
            'options' => [
                'Active Only' => 'ACTIVE',
                'Passive Only' => 'PASSIVE',
                'Active and Passive' => 'ACTIVE_AND_PASSIVE',
                'Not Retained' => 'UNSET'
            ]
        ],
        'isEhrDataAvailable' => [
            'label' => 'EHR Data Transfer',
            'options' => [
                'Yes' => 'yes',
                'No' => 'no'
            ]
        ]
    ];

    public static $consentAdvanceFilters = [
        'consentForStudyEnrollment' => [
            'label' => 'Primary Consent',
            'options' => [
                'View All' => '',
                'Consented' => 'SUBMITTED',
                'Refused Consent' => 'SUBMITTED_NO_CONSENT',
                'Consent Not Completed' => 'UNSET'
            ],
            'dateField' => 'consentForStudyEnrollmentAuthored'
        ],
        'questionnaireOnDnaProgram' => [
            'label' => 'Program Update',
            'options' => [
                'View All' => '',
                'Completed' => 'SUBMITTED',
                'Not Completed' => 'UNSET'
            ],
            'dateField' => 'questionnaireOnDnaProgramAuthored'
        ],
        'consentForElectronicHealthRecords' => [
            'label' => 'EHR Consent Status',
            'options' => [
                'View All' => '',
                'Consented' => 'SUBMITTED',
                'Refused consent' => 'SUBMITTED_NO_CONSENT',
                'Consent not completed' => 'UNSET'
            ],
            'dateField' => 'consentForElectronicHealthRecordsAuthored'
        ],
        'ehrConsentExpireStatus' => [
            'label' => 'EHR Expiration Status',
            'options' => [
                'View All' => '',
                'Active' => 'ACTIVE',
                'Expired' => 'EXPIRED'
            ],
            'dateField' => 'ehrConsentExpireStatusAuthored'
        ],
        'consentForGenomicsROR' => [
            'label' => 'gRoR Consent Status',
            'options' => [
                'View All' => '',
                'Consented Yes' => 'SUBMITTED',
                'Refused Consent' => 'SUBMITTED_NO_CONSENT',
                'Responded Not Sure' => 'SUBMITTED_NOT_SURE',
                'Consent Not Completed' => 'UNSET'
            ],
            'dateField' => 'consentForGenomicsRORAuthored'
        ],
        'consentForDvElectronicHealthRecordsSharing' => [
            'label' => 'DV-Only EHR Sharing',
            'options' => [
                'View All' => '',
                'Consented Yes' => 'SUBMITTED',
                'Refused Consent' => 'SUBMITTED_NO_CONSENT',
                'Responded Not Sure' => 'SUBMITTED_NOT_SURE',
                'Consent Not Completed' => 'UNSET'
            ],
            'dateField' => 'consentForDvElectronicHealthRecordsSharingAuthored'
        ],
        'consentForCABoR' => [
            'label' => 'CABoR Consent',
            'options' => [
                'View All' => '',
                'Consented Yes' => 'SUBMITTED',
                'Refused Consent' => 'SUBMITTED_NO_CONSENT',
                'Responded Not Sure' => 'SUBMITTED_NOT_SURE',
                'Consent Not Completed' => 'UNSET'
            ],
            'dateField' => 'consentForCABoRAuthored'
        ],
        'consentCohort' => [
            'label' => 'Consent Cohort',
            'options' => [
                'View All' => '',
                'Cohort 1' => 'COHORT_1',
                'Cohort 2' => 'COHORT_2',
                'Cohort 2 Pilot' => 'COHORT_2_PILOT',
                'Cohort 3' => 'COHORT_3'
            ]
        ],
        'primaryLanguage' => [
            'label' => 'Language of Primary Cosent',
            'options' => [
                'View All' => '',
                'English' => 'en',
                'Spanish' => 'es'
            ]
        ]
    ];

    //These are currently not working in the RDR
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
        'MedicalHistory' => 'Med History',
        'FamilyHealth' => 'Family History',
        'HealthcareAccess' => 'Access',
        'CopeMay' => 'COPE May',
        'CopeJune' => 'COPE June',
        'CopeJuly' => 'COPE July',
        'CopeNov' => 'COPE Nov',
        'CopeDec' => 'COPE Dec',
        'CopeFeb' => 'COPE Feb',
        'CopeVaccineMinute1' => 'Summer Minute',
        'CopeVaccineMinute2' => 'Fall Minute'
    ];

    public static $initialSurveys = [
        'TheBasics',
        'OverallHealth',
        'Lifestyle',
        'MedicalHistory',
        'FamilyHealth',
        'HealthcareAccess',
        'CopeMay',
        'CopeJune',
        'CopeJuly',
        'CopeNov',
        'CopeDec'
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

    public static $digitalHealthSharingTypes = [
        'fitbit' => 'Fitbit Consent',
        'appleHealthKit' => 'Apple HealthKit Consent',
        'appleEHR' => 'Apple EHR Consent'
    ];

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

    public static function csvEhrConsentExpireStatus($ehrConsentExpireStatus, $consentForElectronicHealthRecords)
    {
        if ($ehrConsentExpireStatus === 'EXPIRED') {
            return 1;
        } elseif ($consentForElectronicHealthRecords === 'SUBMITTED' && empty($ehrConsentExpireStatus)) {
            return 0;
        }
        return '';
    }

    public static function csvRetentionType($value)
    {
        switch ($value) {
            case 'ACTIVE':
                return 2;
            case 'PASSIVE':
                return 1;
            case 'ACTIVE_AND_PASSIVE':
                return 3;
            default:
                return 0;
        }
    }

    public static function csvDeceasedStatus($value)
    {
        switch ($value) {
            case 'PENDING':
                return 1;
            case 'APPROVED':
                return 2;
                break;
            default:
                return 0;
        }
    }

    public static function displayStatus($value, $successStatus, $userTimezone, $time = null, $displayTime = true)
    {
        if ($value === $successStatus) {
            return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $userTimezone, $displayTime);
        } elseif ($value === "{$successStatus}_NOT_SURE") {
            return self::HTML_WARNING . ' ' . self::dateFromString($time, $userTimezone, $displayTime);
        }
        return self::HTML_DANGER;
    }

    public static function displaySurveyStatus($value, $time, $userTimezone, $displayTime = true)
    {
        if ($value === 'SUBMITTED') {
            $status = self::HTML_SUCCESS;
        } elseif ($value === 'SUBMITTED_NOT_SURE') {
            $status = self::HTML_WARNING;
        } else {
            $status = self::HTML_DANGER;
        }
        return $status . ' ' . self::dateFromString($time, $userTimezone, $displayTime);
    }

    public static function displayConsentStatus($value, $time, $userTimezone, $displayTime = true)
    {
        switch ($value) {
            case 'SUBMITTED':
                return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $userTimezone, $displayTime) . ' (Consented Yes)';
            case 'SUBMITTED_NO_CONSENT':
                return self::HTML_DANGER . ' ' . self::dateFromString($time, $userTimezone, $displayTime) . ' (Refused Consent)';
            case 'SUBMITTED_NOT_SURE':
                return self::HTML_WARNING . ' ' . self::dateFromString($time, $userTimezone, $displayTime) . ' (Responded Not Sure)';
            case 'SUBMITTED_INVALID':
                return self::HTML_DANGER . ' ' . self::dateFromString($time, $userTimezone, $displayTime) . ' (Invalid)';
            default:
                return self::HTML_DANGER . ' (Consent Not Completed)';
        }
    }

    public static function displayFirstConsentStatusTime($time, $userTimezone, $type = 'primary', $displayTime = true)
    {
        if (!empty($time)) {
            return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $userTimezone, $displayTime);
        } elseif ($type === 'ehr') {
            return self::HTML_DANGER . ' (never consented yes)';
        }
        return '';
    }

    public static function displayGenomicsConsentStatus($value, $time, $userTimezone, $displayTime = true)
    {
        switch ($value) {
            case 'SUBMITTED':
                return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $userTimezone, $displayTime) . ' (Consented Yes)';
            case 'SUBMITTED_NO_CONSENT':
                return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $userTimezone, $displayTime) . ' (Refused Consent)';
            case 'SUBMITTED_NOT_SURE':
                return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $userTimezone, $displayTime) . ' (Responded Not Sure)';
            case 'SUBMITTED_INVALID':
                return self::HTML_DANGER . ' ' . self::dateFromString($time, $userTimezone, $displayTime) . ' (Invalid)';
            default:
                return self::HTML_DANGER . ' (Consent Not Completed)';
        }
    }

    public static function displayEhrConsentExpireStatus(
        $consentForElectronicHealthRecords,
        $ehrConsentExpireStatus,
        $time,
        $userTimezone,
        $displayTime = true
    ) {
        if ($ehrConsentExpireStatus === 'EXPIRED') {
            return self::HTML_DANGER . ' ' . self::dateFromString($time, $userTimezone, $displayTime) . ' (Expired)';
        } elseif ($consentForElectronicHealthRecords === 'SUBMITTED' && empty($ehrConsentExpireStatus)) {
            return self::HTML_SUCCESS . ' Active';
        }
        return '';
    }

    public static function getActivityStatus($participant, $userTimezone)
    {
        switch ($participant->activityStatus) {
            case 'withdrawn':
                return self::HTML_DANGER . '<span class="text-danger"> Withdrawn </span>' . self::dateFromString(
                    $participant->withdrawalAuthored,
                    $userTimezone
                );
            case 'active':
                return self::HTML_SUCCESS . ' Active';
            case 'deactivated':
                return self::HTML_NOTICE . ' Deactivated ' . self::dateFromString($participant->suspensionTime, $userTimezone);
            case 'deceased':
                if ($participant->dateOfDeath) {
                    $dateOfDeath = date('n/j/Y', strtotime($participant->dateOfDeath));
                    return sprintf(
                        self::HTML_DANGER . ' %s %s',
                        ($participant->deceasedStatus === 'PENDING') ? 'Deceased (Pending Acceptance)' : 'Deceased',
                        $dateOfDeath
                    );
                }
                return sprintf(self::HTML_DANGER . ' %s', ($participant->deceasedStatus === 'PENDING') ? 'Deceased (Pending Acceptance)' : 'Deceased');
            default:
                return '';
        }
    }

    public static function displayProgramUpdate($consentCohort, $questionnaireOnDnaProgram, $questionnaireOnDnaProgramAuthored, $userTimezone)
    {
        if ($consentCohort !== 'COHORT_2') {
            return self::HTML_NOTICE . ' (not applicable) ';
        } elseif ($questionnaireOnDnaProgram === 'SUBMITTED') {
            return self::HTML_SUCCESS . ' ' . self::dateFromString($questionnaireOnDnaProgramAuthored, $userTimezone);
        } else {
            return self::HTML_DANGER . '<span class="text-danger"> (review not completed) </span>';
        }
    }

    public static function getRetentionEligibleStatus($value, $time, $userTimezone)
    {
        if ($value === 'ELIGIBLE') {
            return self::HTML_SUCCESS . ' (Yes) <br/>' . self::dateFromString($time, $userTimezone);
        } elseif ($value === 'NOT_ELIGIBLE') {
            return self::HTML_DANGER . ' (No)';
        }
        return '';
    }

    public static function getRetentionType($value)
    {
        switch ($value) {
            case 'ACTIVE':
                return self::HTML_SUCCESS . ' (Actively Retained)';
            case 'PASSIVE':
                return self::HTML_SUCCESS . ' (Passively Retained)';
            case 'ACTIVE_AND_PASSIVE':
                return self::HTML_SUCCESS . ' (Actively and Passively Retained)';
            default:
                return self::HTML_DANGER . ' (Not Retained)';
        }
    }

    public static function getEhrAvailableStatus($value)
    {
        if ($value) {
            return self::HTML_SUCCESS . ' Yes';
        }
        return self::HTML_DANGER . ' No';
    }

    public static function getExportHeaders()
    {
        $headers = [
            'Last Name',
            'First Name',
            'Middle Initial',
            'Date of Birth',
            'PMI ID',
            'Biobank ID',
            'Participant Status',
            'Core Participant Date',
            'Withdrawal Status',
            'Withdrawal Date',
            'Withdrawal Reason',
            'Deactivation Status',
            'Deactivation Date',
            'Deceased',
            'Date of Death',
            'Date of Death Approval',
            'Participant Origination',
            'Consent Cohort',
            'Date of First Primary Consent',
            'Primary Consent Status',
            'Primary Consent Date',
            'Program Update',
            'Date of Program Update',
            'Date of First EHR Consent',
            'EHR Consent Status',
            'EHR Consent Date',
            'EHR Expiration Status',
            'EHR Expiration Date',
            'gRoR Consent Status',
            'gRoR Consent Date',
            'Language of Primary Consent',
            'DV-only EHR Sharing',
            'DV-only EHR Sharing Date',
            'CABoR Consent Status',
            'CABoR Consent Date',
            'Retention Eligible',
            'Date of Retention Eligibility',
            'Retention Status',
            'EHR Data Transfer',
            'Most Recent EHR Receipt',
            'Patient Status: Yes',
            'Patient Status: No',
            'Patient Status: No Access',
            'Patient Status: Unknown',
            'Street Address',
            'Street Address2',
            'City',
            'State',
            'ZIP',
            'Email',
            'Login Phone',
            'Phone',
            'Required PPI Surveys Complete',
            'Completed Surveys'
        ];
        foreach (self::$surveys as $survey => $label) {
            if (in_array($survey, self::$initialSurveys, true)) {
                $headers[] = $label . ' PPI Survey Complete';
                $headers[] = $label . ' PPI Survey Completion Date';
            }
        }
        $headers[] = 'Paired Site';
        $headers[] = 'Paired Organization';
        $headers[] = 'Physical Measurements Status';
        $headers[] = 'Physical Measurements Completion Date';
        $headers[] = 'Physical Measurements Site';
        $headers[] = 'Samples to Isolate DNA';
        $headers[] = 'Baseline Samples';
        $headers[] = 'Biospecimens Site';
        foreach (self::$samples as $label) {
            $headers[] = $label . ' Received';
            $headers[] = $label . ' Received Date';
        }
        $headers[] = 'Saliva Collection';
        $headers[] = 'Sex';
        $headers[] = 'Gender Identity';
        $headers[] = 'Race/Ethnicity';
        $headers[] = 'Education';
        $headers[] = 'COPE Feb PPI Survey Complete';
        $headers[] = 'COPE Feb PPI Survey Completion Date';
        $headers[] = 'Core Participant Minus PM Date';
        $headers[] = 'Summer Minute PPI Survey Complete';
        $headers[] = 'Summer Minute PPI Survey Completion Date';
        $headers[] = 'Fall Minute PPI Survey Complete';
        $headers[] = 'Fall Minute PPI Survey Completion Date';
        foreach (array_values(self::$digitalHealthSharingTypes) as $label) {
            $headers[] = $label;
            $headers[] = $label . ' Date';
        }
        return $headers;
    }

    public static function getConsentExportHeaders($params)
    {
        $headers = [];
        foreach (self::$columnsDef as $field => $columnDef) {
            if ($columnDef['toggleColumn']) {
                if (isset($params["column{$field}"]) && $params["column{$field}"] === 'on') {
                    if (isset($columnDef['csvLabels'])) {
                        foreach ($columnDef['csvLabels'] as $csvLabel) {
                            $headers[] = $csvLabel;
                        }
                    } else {
                        $headers[] = $columnDef['csvLabel'];
                    }
                }
            } else {
                $headers[] = $columnDef['csvLabel'];
            }
        }
        return $headers;
    }

    public static function getDigitalHealthSharingStatus($digitalHealthSharingStatus, $type, $userTimezone)
    {
        if ($digitalHealthSharingStatus) {
            if (isset($digitalHealthSharingStatus->{$type}->status)) {
                $authoredDate = $digitalHealthSharingStatus->{$type}->history[0]->authoredTime ?? '';
                if ($digitalHealthSharingStatus->{$type}->status === 'YES') {
                    return self::HTML_SUCCESS . ' ' . self::dateFromString($authoredDate, $userTimezone);
                }
                return self::HTML_DANGER . ' ' . self::dateFromString($authoredDate, $userTimezone);
            }
        }
        return self::HTML_DANGER;
    }

    public static function csvDigitalHealthSharingStatus($digitalHealthSharingStatus, $type, $displayDate = false, $userTimezone = null)
    {
        if ($digitalHealthSharingStatus) {
            if (!$displayDate) {
                return isset($digitalHealthSharingStatus->{$type}->status) && $digitalHealthSharingStatus->{$type}->status === 'YES' ? 1 : 0;
            }
            $authoredDate = $digitalHealthSharingStatus->{$type}->history[0]->authoredTime ?? '';
            return self::dateFromString($authoredDate, $userTimezone);
        }
        return !$displayDate ? 0 : '';
    }

    public static function hasDateFields($params)
    {
        foreach (self::$consentAdvanceFilters as $advanceFilter) {
            if (isset($advanceFilter['dateField']) && (!empty($params[$advanceFilter['dateField'] . 'StartDate']) || !empty($params[$advanceFilter['dateField'] . 'EndDate']))) {
                return true;
            }
        }
        return false;
    }

    public static function getDateFilterParams($params)
    {
        $rdrParams = '';
        foreach (self::$consentAdvanceFilters as $advanceFilter) {
            if (isset($advanceFilter['dateField'])) {
                if (!empty($params[$advanceFilter['dateField'] . 'StartDate'])) {
                    $rdrParams .= '&' . $advanceFilter['dateField'] . '=gt' . $params[$advanceFilter['dateField'] . 'StartDate'];
                }
                if (!empty($params[$advanceFilter['dateField'] . 'EndDate'])) {
                    $rdrParams .= '&' . $advanceFilter['dateField'] . '=lt' . $params[$advanceFilter['dateField'] . 'EndDate'];
                }
            }
        }
        return $rdrParams;
    }

    public static function getWorkQueueConsentColumns()
    {
        $workQueueConsentColumns = [];
        foreach (self::$columnsDef as $field => $columnDef) {
            if ($columnDef['toggleColumn']) {
                if (isset($columnDef['displayNames'])) {
                    foreach (array_keys($columnDef['displayNames']) as $subField) {
                        $workQueueConsentColumns[] = 'column' . $subField;
                    }
                } else {
                    $workQueueConsentColumns[] = 'column' . $field;
                }
            }
        }
        return $workQueueConsentColumns;
    }
}
