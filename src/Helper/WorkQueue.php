<?php

namespace App\Helper;

use DateTime;
use DateTimeZone;

class WorkQueue
{
    public const LIMIT_EXPORT = 10000;
    public const LIMIT_EXPORT_PAGE_SIZE = 1000;
    public const FULL_DATA_ACCESS = 'full_data';
    public const LIMITED_DATA_ACCESS = 'limited_data';
    public const DOWNLOAD_DISABLED = 'disabled';
    public const NA_ADULT = 'adult';
    public const NA_PEDIATRIC = 'pediatric';

    public const HTML_SUCCESS = '<i class="fa fa-check text-success" aria-hidden="true"></i>';
    public const HTML_DANGER = '<i class="fa fa-times text-danger" aria-hidden="true"></i>';
    public const HTML_INVALID = '<i class="fa fa-exclamation-circle text-danger" aria-hidden="true"></i>';
    public const HTML_WARNING = '<i class="fa fa-question text-warning" aria-hidden="true"></i>';
    public const HTML_NOTICE = '<i class="fa fa-stop-circle text-warning" aria-hidden="true"></i>';
    public const HTML_PROCESSING = '<i class="fa fa-sync text-warning" aria-hidden="true"></i>';
    public const HTML_SUCCESS_DOUBLE = '<i class="fa fa-check-double text-success" aria-hidden="true"></i>';
    public const HTML_CHILD_ICON = '<i class="fa fa-child child-icon" aria-hidden="true"></i> ';

    public static $columnsDef = [
        'lastName' => [
            'name' => 'Last Name',
            'rdrField' => 'lastName',
            'sortField' => 'lastName',
            'generateLink' => true,
            'toggleColumn' => false,
            'default' => true,
            'displayPediatricIcon' => true
        ],
        'firstName' => [
            'name' => 'First Name',
            'rdrField' => 'firstName',
            'sortField' => 'firstName',
            'generateLink' => true,
            'toggleColumn' => false,
            'default' => true
        ],
        'middleName' => [
            'name' => 'Middle Name',
            'csvName' => 'Middle Initial',
            'rdrField' => 'middleName',
            'sortField' => 'middleName',
            'generateLink' => true,
            'toggleColumn' => false,
            'default' => true
        ],
        'dateOfBirth' => [
            'name' => 'Date of Birth',
            'rdrField' => 'dob',
            'sortField' => 'dateOfBirth',
            'formatDate' => true,
            'csvMethod' => 'csvDateFromObject',
            'toggleColumn' => false,
            'group' => 'details',
            'default' => true
        ],
        'participantId' => [
            'name' => 'PMI ID',
            'rdrField' => 'id',
            'sortField' => 'participantId',
            'toggleColumn' => false,
            'group' => 'details',
            'default' => true
        ],
        'biobankId' => [
            'name' => 'Biobank ID',
            'rdrField' => 'biobankId',
            'sortField' => 'biobankId',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'details'
        ],
        'participantStatus' => [
            'name' => 'Participant Status',
            'rdrField' => 'enrollmentStatusV3_2',
            'sortField' => 'enrollmentStatusV3_2',
            'toggleColumn' => true,
            'type' => 'participantStatus',
            'group' => 'details',
            'default' => true
        ],
        'coreParticipant' => [
            'name' => 'Core Participant Date',
            'rdrDateField' => 'enrollmentStatusCoreV3_2Time',
            'sortField' => 'enrollmentStatusV3_2',
            'toggleColumn' => true
        ],
        'enrollmentStatusEnrolledParticipantV3_2Time' => [
            'name' => 'Enrolled Participant Date',
            'rdrDateField' => 'enrollmentStatusEnrolledParticipantV3_2Time',
        ],
        'enrollmentStatusCoreMinusPmV3_2Time' => [
            'name' => 'Core Participant Minus PM Date',
            'rdrDateField' => 'enrollmentStatusCoreMinusPmV3_2Time',
        ],
        'enrollmentStatusParticipantV3_2Time' => [
            'name' => 'Participant Date',
            'rdrDateField' => 'enrollmentStatusParticipantV3_2Time',
        ],
        'enrollmentStatusParticipantPlusEhrV3_2Time' => [
            'name' => 'Participant + EHR Date',
            'rdrDateField' => 'enrollmentStatusParticipantPlusEhrV3_2Time',
        ],
        'enrollmentStatusPmbEligibleV3_2Time' => [
            'name' => 'PM&B Eligible Date',
            'rdrDateField' => 'enrollmentStatusPmbEligibleV3_2Time',
        ],
        'activityStatus' => [
            'name' => 'Activity Status',
            'rdrField' => 'activityStatus',
            'sortField' => 'activityStatus',
            'htmlClass' => 'text-center',
            'method' => 'getActivityStatus',
            'toggleColumn' => true,
            'type' => 'activityStatus',
            'group' => 'details',
            'default' => true
        ],
        'withdrawalStatus' => [
            'name' => 'Withdrawal Status',
            'csvNames' => [
                'Withdrawal Status',
                'Withdrawal Date'
            ],
            'rdrField' => 'isWithdrawn',
            'rdrDateField' => 'withdrawalAuthored',
            'fieldCheck' => true
        ],
        'withdrawalReason' => [
            'name' => 'Withdrawal Reason',
            'rdrField' => 'withdrawalReason',
            'sortField' => 'withdrawalReason',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'details'
        ],
        'pediatricStatus' => [
            'name' => 'Pediatric Status',
            'rdrField' => 'isPediatric',
            'method' => 'getPediatricStatus',
            'csvMethod' => 'getPediatricStatus',
            'toggleColumn' => true,
            'orderable' => false,
            'default' => true,
            'group' => 'details'
        ],
        'deactivationStatus' => [
            'name' => 'Deactivation Status',
            'csvNames' => [
                'Deactivation Status',
                'Deactivation Date'
            ],
            'rdrField' => 'isSuspended',
            'rdrDateField' => 'suspensionTime',
            'fieldCheck' => true
        ],
        'deceasedStatus' => [
            'name' => 'Deceased',
            'rdrField' => 'deceasedStatus',
            'csvMethod' => 'csvDeceasedStatus'
        ],
        'dateOfDeath' => [
            'name' => 'Date of Death',
            'rdrDateField' => 'dateOfDeath',
        ],
        'dateOfDeathApproval' => [
            'name' => 'Date of Death Approval',
            'rdrField' => 'deceasedStatus',
            'rdrDateField' => 'deceasedAuthored',
            'csvStatusText' => 'APPROVED'
        ],
        'participantOrigin' => [
            'name' => 'Participant Origination',
            'rdrField' => 'participantOrigin',
            'sortField' => 'participantOrigin',
            'toggleColumn' => true,
            'checkDvVisibility' => true,
            'group' => 'details'
        ],
        'consentCohort' => [
            'name' => 'Consent Cohort',
            'rdrField' => 'consentCohortText',
            'sortField' => 'consentCohort',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'group' => 'consent',
            'default' => true
        ],
        'firstPrimaryConsent' => [
            'name' => 'Date of First Primary Consent',
            'rdrField' => 'consentForStudyEnrollmentFirstYesAuthored',
            'csvFormatDate' => true,
        ],
        'primaryConsent' => [
            'name' => 'Primary Consent',
            'csvNames' => [
                'Primary Consent Status',
                'Primary Consent Date'
            ],
            'rdrField' => 'consentForStudyEnrollment',
            'sortField' => 'consentForStudyEnrollmentAuthored',
            'rdrDateField' => 'consentForStudyEnrollmentAuthored',
            'consentMethod' => 'displayHistoricalConsentStatus',
            'reconsentField' => 'reconsentForStudyEnrollmentAuthored',
            'reconsentPdfPath' => 'reconsentForStudyEnrollmentFilePath',
            'displayTime' => true,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'pdfPath' => 'consentForStudyEnrollmentFilePath',
            'group' => 'consent',
            'default' => true,
            'historicalType' => 'primary'
        ],
        'questionnaireOnDnaProgram' => [
            'name' => 'Program Update',
            'csvNames' => [
                'Program Update',
                'Date of Program Update'
            ],
            'rdrField' => 'questionnaireOnDnaProgram',
            'sortField' => 'questionnaireOnDnaProgramAuthored',
            'rdrDateField' => 'questionnaireOnDnaProgramAuthored',
            'otherField' => 'consentCohort',
            'method' => 'displayProgramUpdate',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'group' => 'consent',
            'default' => true,
            'display_na' => self::NA_PEDIATRIC
        ],
        'firstEhrConsent' => [
            'name' => 'Date of First EHR Consent',
            'rdrField' => 'consentForElectronicHealthRecordsFirstYesAuthored',
            'csvFormatDate' => true,
        ],
        'ehrConsent' => [
            'name' => 'EHR Consent',
            'csvNames' => [
                'EHR Consent Status',
                'EHR Consent Date'
            ],
            'rdrField' => 'consentForElectronicHealthRecords',
            'sortField' => 'consentForElectronicHealthRecordsAuthored',
            'rdrDateField' => 'consentForElectronicHealthRecordsAuthored',
            'consentMethod' => 'displayHistoricalConsentStatus',
            'reconsentField' => 'reconsentForElectronicHealthRecordsAuthored',
            'reconsentPdfPath' => 'reconsentForElectronicHealthRecordsFilePath',
            'displayTime' => true,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'pdfPath' => 'consentForElectronicHealthRecordsFilePath',
            'group' => 'consent',
            'default' => true,
            'historicalType' => 'ehr',
            'statusDisplay' => [
                '' => '',
                'SUBMITTED' => '',
                'SUBMITTED_NOT_VALIDATED' => '<span title="The EHR Consent has been submitted and is currently undergoing validation. This process could take up to 24hrs to process." data-toggle="tooltip" data-container="body">(Processing)</span>',
                'SUBMITTED_INVALID' => '<span title="An error has been identified with this EHR Consent and a ticket has been submitted to PTSC for review." data-toggle="tooltip" data-container="body">(Invalid)</span>',
                'SUBMITTED_NO_CONSENT' => '',
                'UNSET' => ''
            ]
        ],
        'ehrConsentExpireStatus' => [
            'name' => 'EHR Expiration Status',
            'csvNames' => [
                'EHR Expiration Status',
                'EHR Expiration Date'
            ],
            'rdrField' => 'ehrConsentExpireStatus',
            'sortField' => 'ehrConsentExpireStatus',
            'rdrDateField' => 'ehrConsentExpireAuthored',
            'otherField' => 'consentForElectronicHealthRecords',
            'method' => 'displayEhrConsentExpireStatus',
            'params' => 4,
            'displayTime' => false,
            'csvMethod' => 'csvEhrConsentExpireStatus',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'consent'
        ],
        'gRoRConsent' => [
            'name' => 'gRoR Consent',
            'csvNames' => [
                'gRoR Consent Status',
                'gRoR Consent Date'
            ],
            'rdrField' => 'consentForGenomicsROR',
            'sortField' => 'consentForGenomicsRORAuthored',
            'rdrDateField' => 'consentForGenomicsRORAuthored',
            'method' => 'displayGenomicsConsentStatus',
            'params' => 5,
            'displayTime' => true,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'pdfPath' => 'consentForGenomicsRORFilePath',
            'group' => 'consent',
            'default' => true,
            'display_na' => self::NA_PEDIATRIC
        ],
        'primaryLanguage' => [
            'name' => 'Language of Primary Consent',
            'rdrField' => 'primaryLanguage',
            'sortField' => 'primaryLanguage',
            'toggleColumn' => true,
            'group' => 'consent',
            'default' => true
        ],
        'dvEhrStatus' => [
            'name' => 'DV-only EHR Sharing',
            'csvNames' => [
                'DV-only EHR Sharing',
                'DV-only EHR Sharing Date'
            ],
            'rdrField' => 'consentForDvElectronicHealthRecordsSharing',
            'sortField' => 'consentForDvElectronicHealthRecordsSharingAuthored',
            'rdrDateField' => 'consentForDvElectronicHealthRecordsSharingAuthored',
            'method' => 'displayConsentStatus',
            'params' => 5,
            'displayTime' => false,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'consent',
            'display_na' => self::NA_PEDIATRIC
        ],
        'caborConsent' => [
            'name' => 'CABoR Consent',
            'csvNames' => [
                'CABoR Consent Status',
                'CABoR Consent Date'
            ],
            'rdrField' => 'consentForCABoR',
            'sortField' => 'consentForCABoRAuthored',
            'rdrDateField' => 'consentForCABoRAuthored',
            'method' => 'displayConsentStatus',
            'params' => 5,
            'displayTime' => true,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'pdfPath' => 'consentForCABoRFilePath',
            'visible' => false,
            'group' => 'consent'
        ],
        'digitalHealthSharingStatus' => [
            'names' => [
                'fitbit' => 'Fitbit Consent',
                'appleHealthKit' => 'Apple HealthKit Consent',
                'appleEHR' => 'Apple EHR Consent'
            ],
            'csvNames' => [
                'Fitbit Consent',
                'Fitbit Consent Date',
                'Apple HealthKit Consent',
                'Apple HealthKit Consent Date',
                'Apple EHR Consent',
                'Apple EHR Consent Date'
            ],
            'rdrField' => 'digitalHealthSharingStatus',
            'method' => 'getDigitalHealthSharingStatus',
            'csvMethod' => 'csvDigitalHealthSharingStatus',
            'htmlClass' => 'text-center',
            'orderable' => false,
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'consent'
        ],
        'EtMConsent' => [
            'name' => 'Exploring the Mind Consent',
            'csvNames' => [
                'Exploring the Mind Consent Status',
                'Exploring the Mind Consent Date'
            ],
            'rdrField' => 'consentForEtM',
            'sortField' => 'consentForEtMAuthored',
            'rdrDateField' => 'consentForEtMAuthored',
            'method' => 'displayConsentStatus',
            'params' => 4,
            'displayTime' => true,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'pdfPath' => 'consentForEtMRORFilePath',
            'visible' => false,
            'group' => 'consent',
            'default' => true,
            'display_na' => self::NA_PEDIATRIC
        ],
        'retentionEligibleStatus' => [
            'name' => 'Retention Eligible',
            'csvNames' => [
                'Retention Eligible',
                'Date of Retention Eligibility'
            ],
            'rdrField' => 'retentionEligibleStatus',
            'sortField' => 'retentionEligibleStatus',
            'rdrDateField' => 'retentionEligibleTime',
            'method' => 'getRetentionEligibleStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'csvStatusText' => 'ELIGIBLE',
            'visible' => false,
            'group' => 'metrics',
            'display_na' => self::NA_PEDIATRIC
        ],
        'retentionType' => [
            'name' => 'Retention Status',
            'rdrField' => 'retentionType',
            'sortField' => 'retentionType',
            'method' => 'getRetentionType',
            'csvMethod' => 'csvRetentionType',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'metrics',
            'display_na' => self::NA_PEDIATRIC
        ],
        'isEhrDataAvailable' => [
            'name' => 'EHR Data Transfer',
            'rdrField' => 'isEhrDataAvailable',
            'sortField' => 'isEhrDataAvailable',
            'method' => 'getEhrAvailableStatus',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'fieldCheck' => true,
            'visible' => false,
            'group' => 'metrics'
        ],
        'latestEhrReceiptTime' => [
            'name' => 'Most Recent EHR Receipt',
            'rdrField' => 'latestEhrReceiptTime',
            'sortField' => 'latestEhrReceiptTime',
            'method' => 'dateFromString',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'userTimezone' => true,
            'visible' => false,
            'csvFormatDate' => true,
            'group' => 'metrics'
        ],
        'healthDataStream' => [
            'name' => 'Health Data Stream',
            'rdrField' => 'healthDataStreamSharingStatus',
            'sortField' => 'healthDataStreamSharingStatusTime',
            'method' => 'getHealthDataSharingStatus',
            'htmlClass' => 'text-center',
            'rdrDateField' => 'healthDataStreamSharingStatusTime',
            'toggleColumn' => true,
            'displayTime' => true,
            'visible' => false,
            'params' => 3,
            'group' => 'metrics',
            'csvNames' => [
                'Health Data Stream Sharing Status',
                'Health Data Stream Sharing Date'
            ],
            'csvMethod' => 'csvHealthDataSharingStatus'
        ],
        'patientStatusYes' => [
            'name' => 'Yes',
            'csvName' => 'Patient Status: Yes',
            'method' => 'getPatientStatus',
            'type' => 'patientStatus',
            'value' => 'YES',
            'visible' => false,
            'group' => 'status'
        ],
        'patientStatusNo' => [
            'name' => 'No',
            'csvName' => 'Patient Status: No',
            'method' => 'getPatientStatus',
            'type' => 'patientStatus',
            'value' => 'NO',
            'visible' => false,
            'group' => 'status'
        ],
        'patientStatusNoAccess' => [
            'name' => 'No Access',
            'csvName' => 'Patient Status: No Access',
            'method' => 'getPatientStatus',
            'type' => 'patientStatus',
            'value' => 'NO_ACCESS',
            'visible' => false,
            'group' => 'status'
        ],
        'patientStatusUnknown' => [
            'name' => 'Unknown',
            'csvName' => 'Patient Status: Unknown',
            'method' => 'getPatientStatus',
            'type' => 'patientStatus',
            'value' => 'UNKNOWN',
            'visible' => false,
            'group' => 'status'
        ],
        'contactMethod' => [
            'name' => 'Contact Method',
            'rdrField' => 'recontactMethod',
            'sortField' => 'recontactMethod',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'contact'
        ],
        'address' => [
            'name' => 'Street Address',
            'rdrField' => 'streetAddress',
            'sortField' => 'streetAddress',
            'toggleColumn' => true,
            'csvRdrField' => 'streetAddress',
            'visible' => false,
            'group' => 'contact'
        ],
        'address2' => [
            'name' => 'Street Address 2',
            'csvName' => 'Street Address2',
            'rdrField' => 'streetAddress2',
            'sortField' => 'streetAddress2',
            'csvRdrField' => 'streetAddress2',
            'group' => 'contact'
        ],
        'city' => [
            'name' => 'City',
            'rdrField' => 'city',
            'sortField' => 'city',
            'csvRdrField' => 'city',
            'group' => 'contact'
        ],
        'state' => [
            'name' => 'State',
            'rdrField' => 'state',
            'csvRdrField' => 'state',
            'group' => 'contact',
            'orderable' => false,
        ],
        'zip' => [
            'name' => 'Zip',
            'rdrField' => 'zipCode',
            'sortField' => 'zipCode',
            'csvRdrField' => 'zipCode',
            'group' => 'contact'
        ],
        'email' => [
            'name' => 'Email',
            'rdrField' => 'email',
            'sortField' => 'email',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'contact'
        ],
        'loginPhone' => [
            'name' => 'Login Phone',
            'rdrField' => 'loginPhoneNumber',
            'sortField' => 'loginPhoneNumber',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'contact',
            'display_na' => self::NA_PEDIATRIC
        ],
        'phone' => [
            'name' => 'Contact Phone',
            'csvName' => 'Phone',
            'rdrField' => 'phoneNumber',
            'sortField' => 'phoneNumber',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'contact',
            'display_na' => self::NA_PEDIATRIC
        ],
        'relatedParticipants' => [
            'name' => 'Related Participants',
            'rdrField' => 'relatedParticipants',
            'wqServiceMethod' => 'getRelatedParticipants',
            'csvMethod' => 'getRelatedParticipants',
            'toggleColumn' => true,
            'orderable' => false,
            'group' => 'contact'
        ],
        'ppiStatus' => [
            'name' => 'Required Complete',
            'csvName' => 'Required PPI Surveys Complete',
            'rdrField' => 'numCompletedBaselinePPIModules',
            'sortField' => 'numCompletedBaselinePPIModules',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'ppiStatus',
            'csvStatusText' => 3,
            'group' => 'surveys',
            'default' => true
        ],
        'ppiSurveys' => [
            'name' => 'Completed Surveys',
            'rdrField' => 'numCompletedPPIModules',
            'sortField' => 'numCompletedPPIModules',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'group' => 'surveys',
            'default' => true
        ],
        'TheBasics' => [
            'name' => 'Basics',
            'csvNames' => [
                'Basics PPI Survey Complete',
                'Basics PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnTheBasics',
            'sortField' => 'questionnaireOnTheBasicsAuthored',
            'rdrDateField' => 'questionnaireOnTheBasicsAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys'
        ],
        'OverallHealth' => [
            'name' => 'Health',
            'csvNames' => [
                'Health PPI Survey Complete',
                'Health PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnOverallHealth',
            'sortField' => 'questionnaireOnOverallHealthAuthored',
            'rdrDateField' => 'questionnaireOnOverallHealthAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys'
        ],
        'Lifestyle' => [
            'name' => 'Lifestyle',
            'csvNames' => [
                'Lifestyle PPI Survey Complete',
                'Lifestyle PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnLifestyle',
            'sortField' => 'questionnaireOnLifestyleAuthored',
            'rdrDateField' => 'questionnaireOnLifestyleAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'MedicalHistory' => [
            'name' => 'Med History',
            'csvNames' => [
                'Med History PPI Survey Complete',
                'Med History PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnMedicalHistory',
            'sortField' => 'questionnaireOnMedicalHistoryAuthored',
            'rdrDateField' => 'questionnaireOnMedicalHistoryAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'FamilyHealth' => [
            'name' => 'Family History',
            'csvNames' => [
                'Family History PPI Survey Complete',
                'Family History PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnFamilyHealth',
            'sortField' => 'questionnaireOnFamilyHealthAuthored',
            'rdrDateField' => 'questionnaireOnFamilyHealthAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'PersonalAndFamilyHealthHistory' => [
            'name' => 'Personal & Family Hx',
            'csvNames' => [
                'Personal & Family Hx PPI Survey Complete',
                'Personal & Family Hx PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnPersonalAndFamilyHealthHistory',
            'sortField' => 'questionnaireOnPersonalAndFamilyHealthHistoryAuthored',
            'rdrDateField' => 'questionnaireOnPersonalAndFamilyHealthHistoryAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'HealthcareAccess' => [
            'name' => 'Access',
            'csvNames' => [
                'Access PPI Survey Complete',
                'Access PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnHealthcareAccess',
            'sortField' => 'questionnaireOnHealthcareAccessAuthored',
            'rdrDateField' => 'questionnaireOnHealthcareAccessAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'SocialDeterminantsOfHealth' => [
            'name' => 'SDOH',
            'csvNames' => [
                'SDOH PPI Survey Complete',
                'SDOH PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnSocialDeterminantsOfHealth',
            'sortField' => 'questionnaireOnSocialDeterminantsOfHealthAuthored',
            'rdrDateField' => 'questionnaireOnSocialDeterminantsOfHealthAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'LifeFunctioning' => [
            'name' => 'Life Functioning',
            'csvNames' => [
                'Life Functioning PPI Survey Complete',
                'Life Functioning PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnLifeFunctioning',
            'sortField' => 'questionnaireOnLifeFunctioningAuthored',
            'rdrDateField' => 'questionnaireOnLifeFunctioningAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'EmotionalHealth' => [
            'name' => 'Emotional Health Hx and Well-being',
            'csvNames' => [
                'Emotional Health History and Well-being PPI Survey Complete',
                'Emotional Health History and Well-being PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnEmotionalHealthHistoryAndWellBeing',
            'sortField' => 'questionnaireOnEmotionalHealthHistoryAndWellBeingAuthored',
            'rdrDateField' => 'questionnaireOnEmotionalHealthHistoryAndWellBeingAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'BehavioralHealth' => [
            'name' => 'Behavioral Health and Personality',
            'csvNames' => [
                'Behavioral Health and Personality Survey Complete',
                'Behavioral Health and Personality Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnBehavioralHealthAndPersonality',
            'sortField' => 'questionnaireOnBehavioralHealthAndPersonalityAuthored',
            'rdrDateField' => 'questionnaireOnBehavioralHealthAndPersonalityAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'EnvironmentalExposures' => [
            'name' => 'Environmental Exposures',
            'csvNames' => [
                'Environmental Exposures PPI Survey Complete',
                'Environmental Exposures PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnEnvironmentalExposures',
            'sortField' => 'questionnaireOnEnvironmentalExposuresAuthored',
            'rdrDateField' => 'questionnaireOnEnvironmentalExposuresAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_ADULT
        ],
        'CopeMay' => [
            'name' => 'COPE May',
            'csvNames' => [
                'COPE May PPI Survey Complete',
                'COPE May PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnCopeMay',
            'sortField' => 'questionnaireOnCopeMayAuthored',
            'rdrDateField' => 'questionnaireOnCopeMayAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'CopeJune' => [
            'name' => 'COPE June',
            'csvNames' => [
                'COPE June PPI Survey Complete',
                'COPE June PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnCopeJune',
            'sortField' => 'questionnaireOnCopeJuneAuthored',
            'rdrDateField' => 'questionnaireOnCopeJuneAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'CopeJuly' => [
            'name' => 'COPE July',
            'csvNames' => [
                'COPE July PPI Survey Complete',
                'COPE July PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnCopeJuly',
            'sortField' => 'questionnaireOnCopeJulyAuthored',
            'rdrDateField' => 'questionnaireOnCopeJulyAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'CopeNov' => [
            'name' => 'COPE Nov',
            'csvNames' => [
                'COPE Nov PPI Survey Complete',
                'COPE Nov PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnCopeNov',
            'sortField' => 'questionnaireOnCopeNovAuthored',
            'rdrDateField' => 'questionnaireOnCopeNovAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'CopeDec' => [
            'name' => 'COPE Dec',
            'csvNames' => [
                'COPE Dec PPI Survey Complete',
                'COPE Dec PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnCopeDec',
            'sortField' => 'questionnaireOnCopeDecAuthored',
            'rdrDateField' => 'questionnaireOnCopeDecAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'CopeFeb' => [
            'name' => 'COPE Feb',
            'csvNames' => [
                'COPE Feb PPI Survey Complete',
                'COPE Feb PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnCopeFeb',
            'sortField' => 'questionnaireOnCopeFebAuthored',
            'rdrDateField' => 'questionnaireOnCopeFebAuthored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'CopeVaccineMinute1' => [
            'name' => 'Summer Minute',
            'csvNames' => [
                'Summer Minute PPI Survey Complete',
                'Summer Minute PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnCopeVaccineMinute1',
            'sortField' => 'questionnaireOnCopeVaccineMinute1Authored',
            'rdrDateField' => 'questionnaireOnCopeVaccineMinute1Authored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'CopeVaccineMinute2' => [
            'name' => 'Fall Minute',
            'csvNames' => [
                'Fall Minute PPI Survey Complete',
                'Fall Minute PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnCopeVaccineMinute2',
            'sortField' => 'questionnaireOnCopeVaccineMinute2Authored',
            'rdrDateField' => 'questionnaireOnCopeVaccineMinute2Authored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'CopeVaccineMinute3' => [
            'name' => 'Winter Minute',
            'csvNames' => [
                'Winter Minute PPI Survey Complete',
                'Winter Minute PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnCopeVaccineMinute3',
            'sortField' => 'questionnaireOnCopeVaccineMinute3Authored',
            'rdrDateField' => 'questionnaireOnCopeVaccineMinute3Authored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'CopeVaccineMinute4' => [
            'name' => 'New Year Minute',
            'csvNames' => [
                'New Year Minute PPI Survey Complete',
                'New Year Minute PPI Survey Completion Date'
            ],
            'rdrField' => 'questionnaireOnCopeVaccineMinute4',
            'sortField' => 'questionnaireOnCopeVaccineMinute4Authored',
            'rdrDateField' => 'questionnaireOnCopeVaccineMinute4Authored',
            'method' => 'displaySurveyStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'surveys',
            'display_na' => self::NA_PEDIATRIC
        ],
        'enrollmentSite' => [
            'name' => 'Enrollment Site',
            'rdrField' => 'enrollmentSiteSuffix',
            'sortField' => 'enrollmentSite',
            'serviceMethod' => 'getSiteDisplayName',
            'toggleColumn' => true,
            'group' => 'enrollment',
            'default' => true,
            'orderable' => false
        ],
        'pairedSite' => [
            'name' => 'Paired Site',
            'rdrField' => 'siteSuffix',
            'sortField' => 'siteSuffix',
            'serviceMethod' => 'getSiteDisplayName',
            'toggleColumn' => true,
            'group' => 'enrollment',
            'default' => true
        ],
        'pairedOrganization' => [
            'name' => 'Paired Organization',
            'rdrField' => 'organization',
            'sortField' => 'organization',
            'serviceMethod' => 'getOrganizationDisplayName',
            'toggleColumn' => true,
            'group' => 'enrollment',
            'default' => true
        ],
        'onsiteIdVerificationTime' => [
            'name' => 'ID Verification',
            'csvName' => 'ID Verification Date',
            'rdrField' => 'onsiteIdVerificationTime',
            'sortField' => 'onsiteIdVerificationTime',
            'method' => 'displayDateStatus',
            'userTimezone' => true,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'csvFormatDate' => true,
            'group' => 'enrollment'
        ],
        'remoteIdVerifiedOn' => [
            'name' => 'Remote ID Verification',
            'csvName' => 'Remote ID Verification Date',
            'rdrField' => 'remoteIdVerifiedOn',
            'sortField' => 'remoteIdVerifiedOn',
            'method' => 'displayDateStatus',
            'userTimezone' => true,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'csvFormatDate' => true,
            'group' => 'enrollment'
        ],
        'participantIncentive' => [
            'name' => 'Incentive',
            'csvName' => 'Incentive Date',
            'rdrField' => 'participantIncentives',
            'sortField' => 'participantIncentives',
            'method' => 'getParticipantIncentive',
            'csvMethod' => 'getParticipantIncentiveDateGiven',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false,
            'orderable' => false,
            'group' => 'enrollment'
        ],
        'selfReportedPhysicalMeasurementsStatus' => [
            'name' => 'Remote Phys Measurements',
            'csvNames' => [
                'Remote Physical Measurements Status',
                'Remote Physical Measurements Completion Date'
            ],
            'rdrField' => 'selfReportedPhysicalMeasurementsStatus',
            'sortField' => 'selfReportedPhysicalMeasurementsStatus',
            'rdrDateField' => 'selfReportedPhysicalMeasurementsAuthored',
            'method' => 'displayStatus',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'statusText' => 'COMPLETED',
            'csvStatusText' => 'COMPLETED',
            'csvDisplayTime' => false,
            'group' => 'enrollment',
            'default' => false
        ],
        'clinicPhysicalMeasurementsStatus' => [
            'name' => 'Phys Measurements',
            'csvNames' => [
                'Physical Measurements Status',
                'Physical Measurements Completion Date'
            ],
            'rdrField' => 'clinicPhysicalMeasurementsStatus',
            'sortField' => 'clinicPhysicalMeasurementsStatus',
            'rdrDateField' => 'clinicPhysicalMeasurementsFinalizedTime',
            'method' => 'displayStatus',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'statusText' => 'COMPLETED',
            'csvStatusText' => 'COMPLETED',
            'csvDisplayTime' => false,
            'group' => 'enrollment',
            'default' => true
        ],
        'evaluationFinalizedSite' => [
            'name' => 'Phys Meas Site',
            'csvName' => 'Physical Measurements Site',
            'rdrField' => 'evaluationFinalizedSite',
            'sortField' => 'clinicPhysicalMeasurementsFinalizedSite',
            'serviceMethod' => 'getSiteDisplayName',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'enrollment',
            'orderable' => false
        ],
        'biobankDnaStatus' => [
            'name' => 'Samples to Isolate DNA?',
            'csvName' => 'Samples to Isolate DNA',
            'rdrField' => 'samplesToIsolateDNA',
            'sortField' => 'samplesToIsolateDNA',
            'method' => 'displayStatus',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'statusText' => 'RECEIVED',
            'csvStatusText' => 'RECEIVED',
            'group' => 'enrollment',
            'default' => true
        ],
        'biobankSamples' => [
            'name' => 'Baseline Samples',
            'rdrField' => 'numBaselineSamplesArrived',
            'sortField' => 'numBaselineSamplesArrived',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'group' => 'enrollment',
            'default' => true
        ],
        'orderCreatedSite' => [
            'name' => 'Bio-specimens Site',
            'csvName' => 'Biospecimens Site',
            'rdrField' => 'orderCreatedSite',
            'sortField' => 'orderCreatedSite',
            'serviceMethod' => 'getSiteDisplayName',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'enrollment',
            'orderable' => false
        ],
        '1SST8' => [
            'name' => '8 mL SST',
            'csvNames' => [
                '8 mL SST Received',
                '8 mL SST Received Date'
            ],
            'rdrField' => 'sampleStatus1SST8',
            'sortField' => 'sampleStatus1SST8Time',
            'rdrDateField' => 'sampleStatus1SST8Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false,
            'group' => 'enrollment',
            'display_na' => self::NA_PEDIATRIC
        ],
        '1PST8' => [
            'name' => '8 mL PST',
            'csvNames' => [
                '8 mL PST Received',
                '8 mL PST Received Date'
            ],
            'rdrField' => 'sampleStatus1PST8',
            'sortField' => 'sampleStatus1PST8Time',
            'rdrDateField' => 'sampleStatus1PST8Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false,
            'group' => 'enrollment',
            'display_na' => self::NA_PEDIATRIC
        ],
        '1HEP4' => [
            'name' => '4 mL Na-Hep',
            'csvNames' => [
                '4 mL Na-Hep Received',
                '4 mL Na-Hep Received Date'
            ],
            'rdrField' => 'sampleStatus1HEP4',
            'sortField' => 'sampleStatus1HEP4Time',
            'rdrDateField' => 'sampleStatus1HEP4Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false,
            'group' => 'enrollment',
            'display_na' => self::NA_PEDIATRIC
        ],
        '1ED02' => [
            'name' => '2 mL EDTA (1ED02)',
            'csvNames' => [
                '2 mL EDTA (1ED02) Received',
                '2 mL EDTA (1ED02) Received Date'
            ],
            'rdrField' => 'sampleStatus1ED02',
            'sortField' => 'sampleStatus1ED02Time',
            'rdrDateField' => 'sampleStatus1ED02Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false,
            'group' => 'enrollment'
        ],
        '2ED02' => [
            'name' => '2 mL EDTA (2ED02)',
            'csvNames' => [
                '2 mL EDTA (2ED02) Received',
                '2 mL EDTA (2ED02) Received Date'
            ],
            'rdrField' => 'sampleStatus2ED02',
            'sortField' => 'sampleStatus2ED02Time',
            'rdrDateField' => 'sampleStatus2ED02Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false,
            'group' => 'enrollment',
            'display_na' => self::NA_ADULT
        ],
        '1ED04' => [
            'name' => '4 mL EDTA (1ED04)',
            'csvNames' => [
                '4 mL EDTA (1ED04) Received',
                '4 mL EDTA (1ED04) Received Date'
            ],
            'rdrField' => 'sampleStatus1ED04',
            'sortField' => 'sampleStatus1ED04Time',
            'rdrDateField' => 'sampleStatus1ED04Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false,
            'group' => 'enrollment'
        ],
        '2ED04' => [
            'name' => '4 mL EDTA (2ED04)',
            'csvNames' => [
                '4 mL EDTA (2ED04) Received',
                '4 mL EDTA (2ED04) Received Date'
            ],
            'rdrField' => 'sampleStatus2ED04',
            'sortField' => 'sampleStatus2ED04Time',
            'rdrDateField' => 'sampleStatus2ED04Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false,
            'group' => 'enrollment',
            'display_na' => self::NA_ADULT
        ],
        '1ED10' => [
            'name' => '1st 10 mL EDTA',
            'csvNames' => [
                '1st 10 mL EDTA Received',
                '1st 10 mL EDTA Received Date'
            ],
            'rdrField' => 'sampleStatus1ED10',
            'sortField' => 'sampleStatus1ED10Time',
            'rdrDateField' => 'sampleStatus1ED10Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false,
            'group' => 'enrollment'
        ],
        '2ED10' => [
            'name' => '2nd 10 mL EDTA',
            'csvNames' => [
                '2nd 10 mL EDTA Received',
                '2nd 10 mL EDTA Received Date'
            ],
            'rdrField' => 'sampleStatus2ED10',
            'sortField' => 'sampleStatus2ED10Time',
            'rdrDateField' => 'sampleStatus2ED10Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false,
            'group' => 'enrollment',
            'display_na' => self::NA_PEDIATRIC
        ],
        '1CFD9' => [
            'name' => 'Cell-Free DNA',
            'csvNames' => [
                'Cell-Free DNA Received',
                'Cell-Free DNA Received Date'
            ],
            'rdrField' => 'sampleStatus1CFD9',
            'sortField' => 'sampleStatus1CFD9Time',
            'rdrDateField' => 'sampleStatus1CFD9Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false,
            'group' => 'enrollment',
            'display_na' => self::NA_PEDIATRIC
        ],
        '1PXR2' => [
            'name' => 'Paxgene RNA',
            'csvNames' => [
                'Paxgene RNA Received',
                'Paxgene RNA Received Date'
            ],
            'rdrField' => 'sampleStatus1PXR2',
            'sortField' => 'sampleStatus1PXR2Time',
            'rdrDateField' => 'sampleStatus1PXR2Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false,
            'group' => 'enrollment'
        ],
        '1UR10' => [
            'name' => 'Urine 10 mL',
            'csvNames' => [
                'Urine 10 mL Received',
                'Urine 10 mL Received Date'
            ],
            'rdrField' => 'sampleStatus1UR10',
            'sortField' => 'sampleStatus1UR10Time',
            'rdrDateField' => 'sampleStatus1UR10Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false,
            'group' => 'enrollment'
        ],
        '1UR90' => [
            'name' => 'Urine 90 mL',
            'csvNames' => [
                'Urine 90 mL Received',
                'Urine 90 mL Received Date'
            ],
            'rdrField' => 'sampleStatus1UR90',
            'sortField' => 'sampleStatus1UR90Time',
            'rdrDateField' => 'sampleStatus1UR90Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false,
            'group' => 'enrollment',
            'display_na' => self::NA_PEDIATRIC
        ],
        '1SAL' => [
            'name' => 'Saliva',
            'csvNames' => [
                'Saliva Received',
                'Saliva Received Date'
            ],
            'rdrField' => 'sampleStatus1SAL',
            'sortField' => 'sampleStatus1SALTime',
            'rdrDateField' => 'sampleStatus1SALTime',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false,
            'group' => 'enrollment'
        ],
        'sample1SAL2CollectionMethod' => [
            'name' => 'Saliva Collection',
            'rdrField' => 'sample1SAL2CollectionMethod'
        ],
        'age' => [
            'name' => 'Age',
            'rdrField' => 'age',
            'sortField' => 'age',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'demographics'
        ],
        'sex' => [
            'name' => 'Sex',
            'rdrField' => 'sex',
            'sortField' => 'sex',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'demographics'
        ],
        'genderIdentity' => [
            'name' => 'Gender Identity',
            'rdrField' => 'genderIdentity',
            'sortField' => 'genderIdentity',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'demographics',
            'display_na' => self::NA_PEDIATRIC
        ],
        'race' => [
            'name' => 'Race/Ethnicity',
            'rdrField' => 'race',
            'sortField' => 'race',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'demographics'
        ],
        'education' => [
            'name' => 'Education',
            'rdrField' => 'education',
            'sortField' => 'education',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'demographics'
        ],
        'fitbit' => [
            'name' => 'Fitbit Consent',
            'csvNames' => [
                'Fitbit Consent',
                'Fitbit Consent Date',
            ],
            'rdrField' => 'digitalHealthSharingStatus',
            'csvMethod' => 'csvDigitalHealthSharingStatus',
        ],
        'appleHealthKit' => [
            'name' => 'Apple HealthKit Consent',
            'csvNames' => [
                'Apple HealthKit Consent',
                'Apple HealthKit Consent Date',
            ],
            'rdrField' => 'digitalHealthSharingStatus',
            'csvMethod' => 'csvDigitalHealthSharingStatus',
        ],
        'appleEHR' => [
            'name' => 'Apple EHR Consent',
            'csvNames' => [
                'Apple EHR Consent',
                'Apple EHR Consent Date',
            ],
            'rdrField' => 'digitalHealthSharingStatus',
            'csvMethod' => 'csvDigitalHealthSharingStatus',
        ],
        'reconsentForStudyEnrollmentAuthored' => [
            'name' => 'Date of Primary Re-Consent',
            'rdrField' => 'reconsentForStudyEnrollmentAuthored',
            'csvFormatDate' => true
        ],
        'reconsentForElectronicHealthRecordsAuthored' => [
            'name' => 'Date of EHR Re-Consent',
            'rdrField' => 'reconsentForStudyEnrollmentAuthored',
            'csvFormatDate' => true
        ],
        'NPHConsent' => [
            'name' => 'Nutrition for Precision Health',
            'group' => 'ancillaryStudies',
            'columnToggle' => true,
            'method' => 'getNphStudyStatus',
            'ancillaryStudy' => true,
            'displayTime' => true,
            'sortField' => 'consentForNphModule1Authored',
            'csvNames' => [
                'nphWithdrawal' => 'NPH Withdrawal Status',
                'nphWithdrawalAuthored' => 'NPH Withdrawal Date',
                'nphDeactivation' => 'NPH Deactivation Status',
                'nphDeactivationAuthored' => 'NPH Deactivation Time',
                'consentForNphModule1' => 'NPH Module 1 Consent Status',
                'consentForNphModule1Authored' => 'NPH Module 1 Consent Date',
            ],
            'csvMethod' => 'getCsvNphStudyStatus',
            'display_na' => self::NA_PEDIATRIC
        ]
    ];

    public static $columns = [
        'lastName',
        'firstName',
        'middleName',
        'dateOfBirth',
        'participantId',
        'biobankId',
        'participantStatus',
        'activityStatus',
        'withdrawalReason',
        'pediatricStatus',
        'participantOrigin',
        'consentCohort',
        'primaryConsent',
        'questionnaireOnDnaProgram',
        'ehrConsent',
        'ehrConsentExpireStatus',
        'gRoRConsent',
        'primaryLanguage',
        'dvEhrStatus',
        'caborConsent',
        'digitalHealthSharingStatus',
        'EtMConsent',
        'retentionEligibleStatus',
        'retentionType',
        'isEhrDataAvailable',
        'latestEhrReceiptTime',
        'healthDataStream',
        'patientStatusYes',
        'patientStatusNo',
        'patientStatusNoAccess',
        'patientStatusUnknown',
        'contactMethod',
        'address',
        'address2',
        'city',
        'state',
        'zip',
        'email',
        'loginPhone',
        'phone',
        'relatedParticipants',
        'ppiStatus',
        'ppiSurveys',
        'TheBasics',
        'OverallHealth',
        'Lifestyle',
        'MedicalHistory',
        'FamilyHealth',
        'PersonalAndFamilyHealthHistory',
        'HealthcareAccess',
        'SocialDeterminantsOfHealth',
        'LifeFunctioning',
        'EmotionalHealth',
        'BehavioralHealth',
        'EnvironmentalExposures',
        'CopeMay',
        'CopeJune',
        'CopeJuly',
        'CopeNov',
        'CopeDec',
        'CopeFeb',
        'CopeVaccineMinute1',
        'CopeVaccineMinute2',
        'CopeVaccineMinute3',
        'CopeVaccineMinute4',
        'enrollmentSite',
        'pairedSite',
        'pairedOrganization',
        'onsiteIdVerificationTime',
        'remoteIdVerifiedOn',
        'participantIncentive',
        'selfReportedPhysicalMeasurementsStatus',
        'clinicPhysicalMeasurementsStatus',
        'evaluationFinalizedSite',
        'biobankDnaStatus',
        'biobankSamples',
        'orderCreatedSite',
        '1SST8',
        '1PST8',
        '1HEP4',
        '1ED02',
        '2ED02',
        '1ED04',
        '2ED04',
        '1ED10',
        '2ED10',
        '1CFD9',
        '1PXR2',
        '1UR10',
        '1UR90',
        '1SAL',
        'age',
        'sex',
        'genderIdentity',
        'race',
        'education',
        'NPHConsent'
    ];

    public static $columnGroups = [
        'details' => 'Participant Details',
        'consent' => 'Consent',
        'metrics' => 'Metrics',
        'status' => 'Patient Status',
        'contact' => 'Contact',
        'surveys' => 'PPI Surveys',
        'enrollment' => 'In Person Enrollment',
        'demographics' => 'Demographics',
        'ancillaryStudies' => 'Ancillary Studies'
    ];

    public static $consentColumns = [
        'lastName',
        'firstName',
        'middleName',
        'dateOfBirth',
        'participantId',
        'primaryConsent',
        'questionnaireOnDnaProgram',
        'ehrConsent',
        'ehrConsentExpireStatus',
        'gRoRConsent',
        'dvEhrStatus',
        'caborConsent',
        'digitalHealthSharingStatus',
        'EtMConsent',
        'consentCohort',
        'primaryLanguage'
    ];

    public static $consentExportColumns = [
        'lastName',
        'firstName',
        'middleName',
        'dateOfBirth',
        'participantId',
        'primaryConsent',
        'questionnaireOnDnaProgram',
        'ehrConsent',
        'ehrConsentExpireStatus',
        'gRoRConsent',
        'dvEhrStatus',
        'caborConsent',
        'fitbit',
        'appleHealthKit',
        'appleEHR',
        'consentCohort',
        'primaryLanguage',
        'firstPrimaryConsent',
        'firstEhrConsent',
        'reconsentForStudyEnrollmentAuthored',
        'reconsentForElectronicHealthRecordsAuthored',
        'EtMConsent'
    ];

    public static $exportColumns = [
        'lastName',
        'firstName',
        'middleName',
        'dateOfBirth',
        'participantId',
        'biobankId',
        'participantStatus',
        'coreParticipant',
        'withdrawalStatus',
        'withdrawalReason',
        'deactivationStatus',
        'deceasedStatus',
        'dateOfDeath',
        'dateOfDeathApproval',
        'participantOrigin',
        'consentCohort',
        'firstPrimaryConsent',
        'primaryConsent',
        'questionnaireOnDnaProgram',
        'firstEhrConsent',
        'ehrConsent',
        'ehrConsentExpireStatus',
        'gRoRConsent',
        'primaryLanguage',
        'dvEhrStatus',
        'caborConsent',
        'retentionEligibleStatus',
        'retentionType',
        'isEhrDataAvailable',
        'latestEhrReceiptTime',
        'patientStatusYes',
        'patientStatusNo',
        'patientStatusNoAccess',
        'patientStatusUnknown',
        'address',
        'address2',
        'city',
        'state',
        'zip',
        'email',
        'loginPhone',
        'phone',
        'ppiStatus',
        'ppiSurveys',
        'TheBasics',
        'OverallHealth',
        'Lifestyle',
        'MedicalHistory',
        'FamilyHealth',
        'HealthcareAccess',
        'EmotionalHealth',
        'BehavioralHealth',
        'CopeMay',
        'CopeJune',
        'CopeJuly',
        'CopeNov',
        'CopeDec',
        'pairedSite',
        'pairedOrganization',
        'clinicPhysicalMeasurementsStatus',
        'evaluationFinalizedSite',
        'biobankDnaStatus',
        'biobankSamples',
        'orderCreatedSite',
        '1SST8',
        '1PST8',
        '1HEP4',
        '1ED02',
        '1ED04',
        '1ED10',
        '2ED10',
        '1CFD9',
        '1PXR2',
        '1UR10',
        '1UR90',
        '1SAL',
        'sample1SAL2CollectionMethod',
        'sex',
        'genderIdentity',
        'race',
        'education',
        'CopeFeb',
        'enrollmentStatusCoreMinusPmV3_2Time',
        'CopeVaccineMinute1',
        'CopeVaccineMinute2',
        'fitbit',
        'appleHealthKit',
        'appleEHR',
        'EtMConsent',
        'PersonalAndFamilyHealthHistory',
        'SocialDeterminantsOfHealth',
        'CopeVaccineMinute3',
        'CopeVaccineMinute4',
        'enrollmentSite',
        'onsiteIdVerificationTime',
        'remoteIdVerifiedOn',
        'participantIncentive',
        'selfReportedPhysicalMeasurementsStatus',
        'reconsentForStudyEnrollmentAuthored',
        'reconsentForElectronicHealthRecordsAuthored',
        'LifeFunctioning',
        'healthDataStream',
        'NPHConsent',
        '2ED02',
        '2ED04',
        'EnvironmentalExposures',
        'enrollmentStatusEnrolledParticipantV3_2Time',
        'enrollmentStatusParticipantV3_2Time',
        'enrollmentStatusParticipantPlusEhrV3_2Time',
        'enrollmentStatusPmbEligibleV3_2Time',
        'pediatricStatus',
        'relatedParticipants'
    ];

    public static $sortColumns = [
        'lastName',
        'firstName',
        'middleName',
        'dateOfBirth',
        'participantId',
        'biobankId',
        'enrollmentStatusV3_2',
        'withdrawalAuthored',
        'withdrawalReason',
        'isPediatric',
        'participantOrigin',
        'consentCohort',
        'consentForStudyEnrollmentAuthored',
        'questionnaireOnDnaProgramAuthored',
        'consentForElectronicHealthRecordsAuthored',
        'ehrConsentExpireStatus',
        'consentForGenomicsRORAuthored',
        'primaryLanguage',
        'consentForDvElectronicHealthRecordsSharingAuthored',
        'consentForCABoRAuthored',
        'digitalHealthSharingStatus',
        'digitalHealthSharingStatus',
        'digitalHealthSharingStatus',
        'consentForEtMAuthored',
        'retentionEligibleTime',
        'retentionType',
        'isEhrDataAvailable',
        'latestEhrReceiptTime',
        'healthDataStreamSharingStatusTime',
        'patientStatus',
        'patientStatus',
        'patientStatus',
        'patientStatus',
        'recontactMethod',
        'streetAddress',
        'streetAddress2',
        'city',
        'stateId',
        'zipCode',
        'email',
        'loginPhoneNumber',
        'phoneNumber',
        'relatedParticipants',
        'numCompletedBaselinePPIModules',
        'numCompletedPPIModules',
        'questionnaireOnTheBasicsAuthored',
        'questionnaireOnOverallHealthAuthored',
        'questionnaireOnLifestyleAuthored',
        'questionnaireOnMedicalHistoryAuthored',
        'questionnaireOnFamilyHealthAuthored',
        'questionnaireOnPersonalAndFamilyHealthHistoryAuthored',
        'questionnaireOnHealthcareAccessAuthored',
        'questionnaireOnSocialDeterminantsOfHealthAuthored',
        'questionnaireOnLifeFunctioningAuthored',
        'questionnaireOnEmotionalHealthHistoryAndWellBeingAuthored',
        'questionnaireOnBehavioralHealthAndPersonalityAuthored',
        'questionnaireOnEnvironmentalExposuresAuthored',
        'questionnaireOnCopeMayAuthored',
        'questionnaireOnCopeJuneAuthored',
        'questionnaireOnCopeJulyAuthored',
        'questionnaireOnCopeNovAuthored',
        'questionnaireOnCopeDecAuthored',
        'questionnaireOnCopeFebAuthored',
        'questionnaireOnCopeVaccineMinute1Authored',
        'questionnaireOnCopeVaccineMinute2Authored',
        'questionnaireOnCopeVaccineMinute3Authored',
        'questionnaireOnCopeVaccineMinute4Authored',
        'enrollmentSite',
        'site',
        'organization',
        'onsiteIdVerificationTime',
        'remoteIdVerifiedOn',
        'participantIncentives',
        'selfReportedPhysicalMeasurementsStatus',
        'clinicPhysicalMeasurementsFinalizedTime',
        'clinicPhysicalMeasurementsFinalizedSite',
        'samplesToIsolateDNA',
        'numBaselineSamplesArrived',
        'biospecimenSourceSite',
        'sampleStatus1SST8Time',
        'sampleStatus1PST8Time',
        'sampleStatus1HEP4Time',
        'sampleStatus1ED02Time',
        'sampleStatus2ED02Time',
        'sampleStatus1ED04Time',
        'sampleStatus2ED04Time',
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
        'consentForNphModule1Authored'
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
        'consentForEtMAuthored',
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
        'enrollmentStatusV3_2' => [
            'label' => 'Participant Status',
            'options' => [
                'Participant' => 'PARTICIPANT',
                'Participant + EHR Consent' => 'PARTICIPANT_PLUS_EHR',
                'Enrolled Participant' => 'ENROLLED_PARTICIPANT',
                'PM&B Eligible' => 'PMB_ELIGIBLE',
                'Core Participant Minus PM' => 'CORE_MINUS_PM',
                'Core Participant' => 'CORE_PARTICIPANT'
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
                'Processing' => 'SUBMITTED_NOT_VALIDATED',
                'Invalid' => 'SUBMITTED_INVALID',
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
        ],

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
        'enrollmentStatusV3_2' => [
            'label' => 'Participant Status',
            'options' => [
                'Participant' => 'PARTICIPANT',
                'Participant + EHR Consent' => 'PARTICIPANT_PLUS_EHR',
                'Enrolled Participant' => 'ENROLLED_PARTICIPANT',
                'PM&B Eligible' => 'PMB_ELIGIBLE',
                'Core Participant Minus PM' => 'CORE_MINUS_PM',
                'Core Participant' => 'CORE_PARTICIPANT'
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

    //TODO rename to advancedFilters
    public static $consentAdvanceFilters = [
        'Status' => [
            'enrollmentStatusV3_2' => [
                'label' => 'Participant Status',
                'options' => [
                    'View All' => '',
                    'Participant' => 'PARTICIPANT',
                    'Participant + EHR Consent' => 'PARTICIPANT_PLUS_EHR',
                    'Enrolled Participant' => 'ENROLLED_PARTICIPANT',
                    'PM&B Eligible' => 'PMB_ELIGIBLE',
                    'Core Participant Minus PM' => 'CORE_MINUS_PM',
                    'Core Participant' => 'CORE_PARTICIPANT',
                ]
            ],
            'activityStatus' => [
                'label' => 'Activity Status',
                'options' => [
                    'View All' => '',
                    'Active' => 'active',
                    'Deactivated' => 'deactivated',
                    'Withdrawn' => 'withdrawn',
                    'Not Withdrawn' => 'not_withdrawn',
                    'Deceased' => 'deceased',
                    'Deceased (Pending)' => 'deceased_pending'
                ]
            ],
            'patientStatus' => [
                'label' => 'Patient Status',
                'options' => [
                    'View All' => '',
                    'Yes' => 'YES',
                    'No' => 'NO',
                    'No Access' => 'NO_ACCESS',
                    'Unknown' => 'UNKNOWN',
                    'Not Completed' => 'UNSET'
                ]
            ],
            'pediatricStatus' => [
                'label' => 'Pediatric Status',
                'options' => [
                    'View All' => '',
                    'Pediatric Participant' => 'SUBMITTED',
                    'Adult Participant' => 'UNSET'
                ]
            ]
        ],
        'Consents' => [
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
                    'Processing' => 'SUBMITTED_NOT_VALIDATED',
                    'Invalid' => 'SUBMITTED_INVALID',
                    'Refused consent' => 'SUBMITTED_NO_CONSENT',
                    'Consent not completed' => 'UNSET'
                ],
                'dateField' => 'consentForElectronicHealthRecordsAuthored'
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
            'EtMConsent' => [
                'label' => 'Exploring The Mind Consent',
                'options' => [
                    'View All' => '',
                    'Consented Yes' => 'SUBMITTED',
                    'Refused Consent' => 'SUBMITTED_NO_CONSENT',
                    'Consent Not Completed' => 'UNSET'
                ],
                'dateField' => 'consentForEtMAuthored'
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
                'label' => 'Language of Primary Consent',
                'options' => [
                    'View All' => '',
                    'English' => 'en',
                    'Spanish' => 'es'
                ]
            ]
        ],
        'Demographics' => [
            'ageRange' => [
                'label' => 'Age',
                'options' => [
                    'View All' => '',
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
            'race' => [
                'label' => 'Race',
                'options' => [
                    'View All' => '',
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
            'genderIdentity' => [
                'label' => 'Gender Identity',
                'options' => [
                    'View All' => '',
                    'Man' => 'GenderIdentity_Man',
                    'Woman' => 'GenderIdentity_Woman',
                    'Non-binary' => 'GenderIdentity_NonBinary',
                    'Transgender' => 'GenderIdentity_Transgender',
                    'More Than One Gender Identity' => 'GenderIdentity_MoreThanOne',
                    'Other' => 'GenderIdentity_AdditionalOptions'
                ]
            ]
        ],
        'EHR' => [
            'isEhrDataAvailable' => [
                'label' => 'EHR Data Transfer',
                'options' => [
                    'View All' => '',
                    'Yes' => 'yes',
                    'No' => 'no'
                ]
            ],
            'ehrConsentExpireStatus' => [
                'label' => 'EHR Expiration Status',
                'options' => [
                    'View All' => '',
                    'Active' => 'ACTIVE',
                    'Expired' => 'EXPIRED'
                ],
                'dateField' => 'ehrConsentExpireStatusAuthored'
            ]
        ],
        'Retention' => [
            'retentionType' => [
                'label' => 'Retention Status',
                'options' => [
                    'View All' => '',
                    'Active Only' => 'ACTIVE',
                    'Passive Only' => 'PASSIVE',
                    'Active and Passive' => 'ACTIVE_AND_PASSIVE',
                    'Not Retained' => 'UNSET'
                ]
            ],
            'retentionEligibleStatus' => [
                'label' => 'Retention Eligible',
                'options' => [
                    'View All' => '',
                    'Yes' => 'ELIGIBLE',
                    'No' => 'NOT_ELIGIBLE'
                ]
            ]
        ],
        'Pairing' => [
            'participantOrigin' => [
                'label' => 'Participant Origination',
                'options' => [
                    'View All' => '',
                    'PTSC Portal' => 'vibrent',
                    'DV Pilot Portal' => 'careevolution'
                ]
            ],
            'enrollmentSite' => [
                'label' => 'Enrollment Site',
                'options' => [
                    'View All' => '',
                    'Unpaired' => 'UNSET'
                ]
            ],
        ],
        'PMB' => [
            'selfReportedPhysicalMeasurementsStatus' => [
                'label' => 'Remote Phys Measurements',
                'options' => [
                    'View All' => '',
                    'Completed' => 'COMPLETED',
                    'Not Completed' => 'UNSET'
                ]
            ],
            'clinicPhysicalMeasurementsStatus' => [
                'label' => 'Phys Measurements',
                'options' => [
                    'View All' => '',
                    'Completed' => 'COMPLETED',
                    'Not Completed' => 'UNSET'
                ]
            ],
            'sampleStatus1SST8' => [
                'label' => '8 mL SST',
                'options' => [
                    'View All' => '',
                    'Received' => 'RECEIVED',
                    'Not Received' => 'UNSET'
                ]
            ],
            'sampleStatus1PST8' => [
                'label' => '8 mL PST',
                'options' => [
                    'View All' => '',
                    'Received' => 'RECEIVED',
                    'Not Received' => 'UNSET'
                ]
            ],
            'sampleStatus1HEP4' => [
                'label' => '4 mL Na-Hep',
                'options' => [
                    'View All' => '',
                    'Received' => 'RECEIVED',
                    'Not Received' => 'UNSET'
                ]
            ],
            'sampleStatus1ED02' => [
                'label' => '2 mL EDTA (1ED02)',
                'options' => [
                    'View All' => '',
                    'Received' => 'RECEIVED',
                    'Not Received' => 'UNSET'
                ]
            ],
            'sampleStatus2ED02' => [
                'label' => '2 mL EDTA (2ED02)',
                'options' => [
                    'View All' => '',
                    'Received' => 'RECEIVED',
                    'Not Received' => 'UNSET'
                ]
            ],
            'sampleStatus1ED04' => [
                'label' => '4 mL EDTA (1ED04)',
                'options' => [
                    'View All' => '',
                    'Received' => 'RECEIVED',
                    'Not Received' => 'UNSET'
                ]
            ],
            'sampleStatus2ED04' => [
                'label' => '4 mL EDTA (2ED04)',
                'options' => [
                    'View All' => '',
                    'Received' => 'RECEIVED',
                    'Not Received' => 'UNSET'
                ]
            ],
            'sampleStatus1ED10' => [
                'label' => '1st 10 mL EDTA',
                'options' => [
                    'View All' => '',
                    'Received' => 'RECEIVED',
                    'Not Received' => 'UNSET'
                ]
            ],
            'sampleStatus2ED10' => [
                'label' => '2nd 10 mL EDTA',
                'options' => [
                    'View All' => '',
                    'Received' => 'RECEIVED',
                    'Not Received' => 'UNSET'
                ]
            ],
            'sampleStatus1CFD9' => [
                'label' => 'Cell-Free DNA',
                'options' => [
                    'View All' => '',
                    'Received' => 'RECEIVED',
                    'Not Received' => 'UNSET'
                ]
            ],
            'sampleStatus1PXR2' => [
                'label' => 'Paxgene RNA',
                'options' => [
                    'View All' => '',
                    'Received' => 'RECEIVED',
                    'Not Received' => 'UNSET'
                ]
            ],
            'sampleStatus1UR10' => [
                'label' => 'Urine 10 mL',
                'options' => [
                    'View All' => '',
                    'Received' => 'RECEIVED',
                    'Not Received' => 'UNSET'
                ]
            ],
            'sampleStatus1UR90' => [
                'label' => 'Urine 90 mL',
                'options' => [
                    'View All' => '',
                    'Received' => 'RECEIVED',
                    'Not Received' => 'UNSET'
                ]
            ],
            'sampleStatus1SAL' => [
                'label' => 'Saliva',
                'options' => [
                    'View All' => '',
                    'Received' => 'RECEIVED',
                    'Not Received' => 'UNSET'
                ]
            ]
        ],
        'Ancillary Studies' => [
            'NphStudyStatus' => [
                'label' => 'Nutrition For Precision Health',
                'options' => [
                    'View All' => '',
                    'Not Consented' => 'NOT_CONSENTED',
                    'Module 1 Consented' => 'MODULE_1_CONSENTED',
                ]
            ],
        ]
    ];

    public static array $rdrPmbFilterParams = [
        'selfReportedPhysicalMeasurementsStatus',
        'clinicPhysicalMeasurementsStatus',
        'sampleStatus1SST8',
        'sampleStatus1PST8',
        'sampleStatus1HEP4',
        'sampleStatus1ED02',
        'sampleStatus2ED02',
        'sampleStatus1ED04',
        'sampleStatus2ED04',
        'sampleStatus1ED10',
        'sampleStatus2ED10',
        'sampleStatus1CFD9',
        'sampleStatus1PXR2',
        'sampleStatus1UR10',
        'sampleStatus1UR90',
        'sampleStatus1SAL'
    ];

    public static $filterDateFieldLabels = [
        'consentForStudyEnrollmentAuthoredStartDate' => 'Primary Consent Start Date',
        'consentForStudyEnrollmentAuthoredEndDate' => 'Primary Consent End Date',
        'questionnaireOnDnaProgramAuthoredStartDate' => 'Program Update Start Date',
        'questionnaireOnDnaProgramAuthoredEndDate' => 'Program Update End Date',
        'consentForElectronicHealthRecordsAuthoredStartDate' => 'EHR Consent Status Start Date',
        'consentForElectronicHealthRecordsAuthoredEndDate' => 'EHR Consent Status End Date',
        'consentForGenomicsRORAuthoredStartDate' => 'gRoR Consent Status Start Date',
        'consentForGenomicsRORAuthoredEndDate' => 'gRoR Consent Status End Date',
        'consentForDvElectronicHealthRecordsSharingAuthoredStartDate' => 'DV-Only EHR Sharing Start Date',
        'consentForDvElectronicHealthRecordsSharingAuthoredEndDate' => 'DV-Only EHR Sharing End Date',
        'consentForCABoRAuthoredStartDate' => 'CABoR Consent Start Date',
        'consentForCABoRAuthoredEndDate' => 'CABoR Consent End Date',
        'ehrConsentExpireStatusAuthoredStartDate' => 'EHR Expiration Status Start Date',
        'ehrConsentExpireStatusAuthoredEndDate' => 'EHR Expiration Status End Date'
    ];

    public static $filterIcons = [
        'Status' => 'fa-user-check',
        'Consents' => 'fa-file-contract',
        'Demographics' => 'fa-globe',
        'EHR' => 'fa-laptop-medical',
        'Retention' => 'fa-check-double',
        'Pairing' => 'fa-building',
        'Ancillary Studies' => 'fa-microscope',
        'PMB' => 'fa-vial'
    ];

    public static array $customFilterLabels = [
        'PMB' => 'PM&B'
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
        'PersonalAndFamilyHealthHistory' => 'Personal & Family Hx',
        'HealthcareAccess' => 'Access',
        'SocialDeterminantsOfHealth' => 'SDOH',
        'LifeFunctioning' => 'Life Functioning',
        'EmotionalHealthHistoryAndWellBeing' => 'Emotional Health Hx and Well-being',
        'BehavioralHealthAndPersonality' => 'Behavioral Health and Personality',
        'EnvironmentalExposures' => 'Environmental Exposures',
        'CopeMay' => 'COPE May',
        'CopeJune' => 'COPE June',
        'CopeJuly' => 'COPE July',
        'CopeNov' => 'COPE Nov',
        'CopeDec' => 'COPE Dec',
        'CopeFeb' => 'COPE Feb',
        'CopeVaccineMinute1' => 'Summer Minute',
        'CopeVaccineMinute2' => 'Fall Minute',
        'CopeVaccineMinute3' => 'Winter Minute',
        'CopeVaccineMinute4' => 'New Year Minute'
    ];

    public static array $pedsSurveys = [
        'TheBasics' => 'Basics',
        'OverallHealth' => 'Health',
        'EnvironmentalExposures' => 'Environmental Exposures'
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
        '1ED02' => '2 mL EDTA (1ED02)',
        '2ED02' => '2 mL EDTA (2ED02)',
        '1ED04' => '4 mL EDTA (1ED04)',
        '2ED04' => '4 mL EDTA (2ED04)',
        '1ED10' => '1st 10 mL EDTA',
        '2ED10' => '2nd 10 mL EDTA',
        '1CFD9' => 'Cell-Free DNA',
        '1PXR2' => 'Paxgene RNA',
        '1UR10' => 'Urine 10 mL',
        '1UR90' => 'Urine 90 mL',
        '1SAL' => 'Saliva'
    ];

    public static array $pedsSamples = [
        '1ED02' => '2 mL EDTA (1ED02)',
        '2ED02' => '2 mL EDTA (2ED02)',
        '1ED04' => '4 mL EDTA (1ED04)',
        '2ED04' => '4 mL EDTA (2ED04)',
        '1ED10' => '1st 10 mL EDTA',
        '1PXR2' => 'Paxgene RNA',
        '1UR10' => 'Urine 10 mL',
        '1SAL' => 'Saliva'
    ];

    public static array $pedsOnlyFields = [
        'EnvironmentalExposures',
        '2ED02',
        '2ED04'
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

    public static $buttonGroups = [
        'default' => [
            'dateOfBirth',
            'participantId',
            'participantStatus',
            'activityStatus',
            'pediatricStatus',
            'consentCohort',
            'primaryConsent',
            'questionnaireOnDnaProgram',
            'ehrConsent',
            'gRoRConsent',
            'primaryLanguage',
            'ppiStatus',
            'ppiSurveys',
            'pairedSite',
            'pairedOrganization',
            'clinicPhysicalMeasurementsStatus',
            'biobankDnaStatus',
            'biobankSamples'
        ],
        'consent' => [
            'dateOfBirth',
            'participantStatus',
            'activityStatus',
            'withdrawalReason'
        ],
        'metrics' => [
            'dateOfBirth',
            'participantId',
            'participantStatus',
            'activityStatus',
            'consentCohort',
            'primaryConsent',
            'questionnaireOnDnaProgram',
            'ehrConsent',
            'ehrConsentExpireStatus',
            'gRoRConsent',
            'EtMConsent',
            'primaryLanguage',
        ],
        'status' => [
            'dateOfBirth',
            'participantId',
            'participantStatus',
            'activityStatus',
            'consentCohort',
            'primaryConsent',
            'questionnaireOnDnaProgram',
            'ehrConsent',
            'ehrConsentExpireStatus',
            'gRoRConsent',
            'primaryLanguage',
            'isEhrDataAvailable',
            'latestEhrReceiptTime',
            'healthDataStream'
        ],
        'contact' => [
            'participantId',
            'retentionEligibleStatus',
            'retentionType'
        ],
        'surveys' => [
            'dateOfBirth',
            'participantStatus',
        ],
        'enrollment' => [
            'dateOfBirth',
            'participantStatus',
        ],
        'demographics' => [
            'dateOfBirth',
            'participantStatus',
        ]
    ];

    public static $defaultColumns = [
        'lastName',
        'firstName',
        'middleName'
    ];

    public static $defaultConsentColumns = [
        'lastName',
        'firstName',
        'middleName',
        'dateOfBirth',
        'participantId'
    ];

    public static $tableExportMap = [
        'primaryConsent' => [
            'primaryConsent',
            'firstPrimaryConsent',
            'reconsentForStudyEnrollmentAuthored'
        ],
        'ehrConsent' => [
            'ehrConsent',
            'firstEhrConsent',
            'reconsentForElectronicHealthRecordsAuthored'
        ],
        'digitalHealthSharingStatus' => [
            'fitbit',
            'appleHealthKit',
            'appleEHR'
        ],
        'participantStatus' => [
            'participantStatus',
            'coreParticipant',
            'enrollmentStatusEnrolledParticipantV3_2Time',
            'enrollmentStatusCoreMinusPmV3_2Time',
            'enrollmentStatusParticipantV3_2Time',
            'enrollmentStatusParticipantPlusEhrV3_2Time',
            'enrollmentStatusPmbEligibleV3_2Time'
        ],
        'activityStatus' => [
            'withdrawalStatus',
            'deactivationStatus',
            'deceasedStatus',
            'dateOfDeath',
            'dateOfDeathApproval'
        ],
        '1SAL' => [
            '1SAL',
            'sample1SAL2CollectionMethod'
        ]
    ];

    public static $withdrawnParticipantFields = [
        'activityStatus',
        'organization',
        'participantId',
        'firstName',
        'lastName',
        'dateOfBirth'
    ];

    public static $consentStatusDisplayText = [
        'SUBMITTED' => '(Consented Yes)',
        'SUBMITTED_NO_CONSENT' => '(Refused Consent)',
        'SUBMITTED_NOT_VALIDATED' => '(Processing)',
        'SUBMITTED_NOT_SURE' => '(Responded Not Sure)',
        'SUBMITTED_INVALID' => '(Invalid)'
    ];

    public static function dateFromString($string, $timezone = null, $displayTime = true, $link = null)
    {
        if (!empty($string)) {
            try {
                if ($timezone) {
                    $date = new DateTime($string);
                    $date->setTimezone(new DateTimeZone($timezone));
                    if ($displayTime) {
                        return $link
                            ? sprintf('<a href="%s" target="_blank">', $link) . $date->format('n/j/Y g:i a') . '</a>'
                            : $date->format('n/j/Y g:i a');
                    }
                    return $link
                        ? sprintf('<a href="%s" target="_blank">', $link) . $date->format('n/j/Y') . '</a>'
                        : $date->format('n/j/Y');
                }
                return date('n/j/Y', strtotime($string));
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

    public static function displayConsentStatus($value, $time, $userTimezone, $displayTime = true, $link = null, $statusDisplay = '')
    {
        if (empty($statusDisplay) && !empty($value)) {
            $statusDisplay = self::$consentStatusDisplayText[$value];
        }
        switch ($value) {
            case 'SUBMITTED':
                return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link)
                    . ' ' . $statusDisplay;
            case 'SUBMITTED_INVALID':
                return self::HTML_INVALID . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link)
                    . ' ' . $statusDisplay;
            case 'SUBMITTED_NO_CONSENT':
                return self::HTML_DANGER . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link)
                    . ' ' . $statusDisplay;
            case 'SUBMITTED_NOT_SURE':
                return self::HTML_WARNING . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link)
                    . ' ' . $statusDisplay;
            case 'SUBMITTED_NOT_VALIDATED':
                return self::HTML_PROCESSING . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link)
                    . ' ' . $statusDisplay;
            default:
                return self::HTML_DANGER . ' (Consent Not Completed)';
        }
    }

    public static function displayHistoricalConsentStatus(
        $participantId,
        $reconsentTime,
        $reconsentPdfLink,
        $consentStatus,
        $consentTime,
        $consentPdfLink,
        $historyType,
        $userTimezone,
        $statusDisplay
    ): string {
        if ($reconsentTime) {
            $html = self::HTML_SUCCESS . ' ' . self::dateFromString(
                $reconsentTime,
                $userTimezone,
                true,
                $reconsentPdfLink
            ) . ' (Consented Yes)';
        } else {
            $html = static::displayConsentStatus($consentStatus, $consentTime, $userTimezone, true, $consentPdfLink, $statusDisplay);
        }
        if ($reconsentTime || $consentTime) {
            $html .= '<br><a data-href="/workqueue/participant/' . $participantId . '/consent-histories/' .
                $historyType . '" class="view-consent-histories">View Historical</a>';
        }
        return $html;
    }

    public static function displayGenomicsConsentStatus($value, $time, $userTimezone, $displayTime = true, $link = null)
    {
        switch ($value) {
            // Note the icons differ from ::displayConsentStatus
            case 'SUBMITTED':
                return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link)
                    . ' ' . self::$consentStatusDisplayText['SUBMITTED'];
            case 'SUBMITTED_NO_CONSENT':
                return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link)
                    . ' ' . self::$consentStatusDisplayText['SUBMITTED_NO_CONSENT'];
            case 'SUBMITTED_NOT_SURE':
                return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link)
                    . ' ' . self::$consentStatusDisplayText['SUBMITTED_NOT_SURE'];
            case 'SUBMITTED_INVALID':
                return self::HTML_DANGER . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link)
                    . ' ' . self::$consentStatusDisplayText['SUBMITTED_INVALID'];
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
        }
        return self::HTML_DANGER . '<span class="text-danger"> (review not completed) </span>';
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

    public static function getHealthDataSharingStatus(string|null $value, string|null $time, string $userTimezone): string
    {
        switch ($value) {
            case 'EVER_SHARED':
                return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $userTimezone) . ' (Ever Shared) ';
            case 'CURRENTLY_SHARING':
                return self::HTML_SUCCESS_DOUBLE . ' ' . self::dateFromString($time, $userTimezone) . ' (Currently Sharing) ';
            case 'NEVER_SHARED':
            default:
                return self::HTML_DANGER . ' (Never Shared)';
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
        $headers = [];
        foreach (self::$exportColumns as $field) {
            $columnDef = self::$columnsDef[$field];
            if (isset($columnDef['csvNames'])) {
                foreach ($columnDef['csvNames'] as $csvName) {
                    $headers[] = $csvName;
                }
            } elseif (isset($columnDef['csvName'])) {
                $headers[] = $columnDef['csvName'];
            } else {
                $headers[] = $columnDef['name'];
            }
        }
        return $headers;
    }

    public static function getConsentExportHeaders($sessionConsentColumns)
    {
        $headers = [];
        self::mapExportColumns($sessionConsentColumns);
        foreach (self::$consentExportColumns as $field) {
            $columnDef = self::$columnsDef[$field];
            if (in_array($field, $sessionConsentColumns)) {
                if (isset($columnDef['csvNames'])) {
                    foreach ($columnDef['csvNames'] as $csvName) {
                        $headers[] = $csvName;
                    }
                } elseif (isset($columnDef['csvName'])) {
                    $headers[] = $columnDef['csvName'];
                } else {
                    $headers[] = $columnDef['name'];
                }
            }
        }
        return $headers;
    }

    public static function mapExportColumns(&$columns): void
    {
        foreach ($columns as $column) {
            if (isset(self::$tableExportMap[$column])) {
                foreach (self::$tableExportMap[$column] as $subColumn) {
                    $columns[] = $subColumn;
                }
            }
        }
        $columns = array_unique($columns);
    }

    public static function getSessionExportHeaders($sessionConsentColumns)
    {
        $headers = [];
        self::mapExportColumns($sessionConsentColumns);
        foreach (self::$exportColumns as $field) {
            $columnDef = self::$columnsDef[$field];
            if (in_array($field, $sessionConsentColumns)) {
                if (isset($columnDef['csvNames'])) {
                    foreach ($columnDef['csvNames'] as $csvName) {
                        $headers[] = $csvName;
                    }
                } elseif (isset($columnDef['csvName'])) {
                    $headers[] = $columnDef['csvName'];
                } else {
                    $headers[] = $columnDef['name'];
                }
            }
        }
        return $headers;
    }

    public static function getDigitalHealthSharingStatus($digitalHealthSharingStatus, $type, $userTimezone): string
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

    public static function csvHealthDataSharingStatus(string|null $healthDataSharingStatus, string $type, bool $displayDate = false, string $userTimezone = null): string|int
    {
        if ($displayDate === false) {
            switch ($healthDataSharingStatus) {
                case 'EVER_SHARED':
                    return 1;
                case 'CURRENTLY_SHARING':
                    return 2;
                case 'NEVER_SHARED':
                default:
                    return 0;
            }
        } else {
            if (!is_null($healthDataSharingStatus)) {
                return self::dateFromString($healthDataSharingStatus, $userTimezone);
            }
            return '';
        }
    }

    public static function hasDateFields($params)
    {
        foreach (self::$consentAdvanceFilters['Consents'] as $advanceFilter) {
            if (isset($advanceFilter['dateField']) && (!empty($params[$advanceFilter['dateField'] . 'StartDate']) || !empty($params[$advanceFilter['dateField'] . 'EndDate']))) {
                return true;
            }
        }
        return false;
    }

    public static function getDateFilterParams($params)
    {
        $rdrParams = '';
        foreach (self::$consentAdvanceFilters['Consents'] as $advanceFilter) {
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
        $defaultConsentColumns = array_merge(self::$defaultConsentColumns, self::$consentColumns);
        foreach ($defaultConsentColumns as $column) {
            $columnDef = self::$columnsDef[$column];
            if (isset($columnDef['names'])) {
                foreach (array_keys($columnDef['names']) as $subColumn) {
                    $workQueueConsentColumns[] = $subColumn;
                }
            } else {
                $workQueueConsentColumns[] = $column;
            }
        }
        return $workQueueConsentColumns;
    }

    public static function getWorkQueueColumns()
    {
        $workQueueColumns = [];
        $defaultColumns = array_merge(self::$defaultColumns, self::$buttonGroups['default']);
        foreach ($defaultColumns as $column) {
            $columnDef = self::$columnsDef[$column];
            if (isset($columnDef['names'])) {
                foreach (array_keys($columnDef['names']) as $subColumn) {
                    $workQueueColumns[] = $subColumn;
                }
            } else {
                $workQueueColumns[] = $column;
            }
        }
        return $workQueueColumns;
    }

    public static function getWorkQueueAllColumns(): array
    {
        $workQueueColumns = [];
        foreach (self::$columns as $column) {
            $columnDef = self::$columnsDef[$column];
            if (isset($columnDef['names'])) {
                foreach (array_keys($columnDef['names']) as $subColumn) {
                    $workQueueColumns[] = $subColumn;
                }
            } else {
                $workQueueColumns[] = $column;
            }
        }
        return $workQueueColumns;
    }

    public static function isValidDate($date)
    {
        $dt = DateTime::createFromFormat('m/d/Y', $date);
        return $dt !== false && !array_sum($dt::getLastErrors());
    }

    public static function isValidDates($params)
    {
        if (!empty($params['dateOfBirth']) && !self::isValidDate($params['dateOfBirth'])) {
            return false;
        }
        foreach (array_values(self::$consentColumns) as $field) {
            $columnDef = self::$columnsDef[$field];
            if (isset($columnDef['rdrDateField'])) {
                if (!empty($params[$columnDef['rdrDateField'] . 'StartDate']) && !self::isValidDate($params[$columnDef['rdrDateField'] . 'StartDate'])) {
                    return false;
                }
                if (!empty($params[$columnDef['rdrDateField'] . 'EndDate']) && !self::isValidDate($params[$columnDef['rdrDateField'] . 'EndDate'])) {
                    return false;
                }
            }
        }
        return true;
    }

    public static function getWorkQueueGroupColumns($groupName)
    {
        if (!isset(self::$buttonGroups[$groupName])) {
            return [];
        }
        $columns = [];
        foreach (self::$columnsDef as $field => $columnDef) {
            if (isset($columnDef['group']) && $columnDef['group'] === $groupName) {
                $columns[] = $field;
            }
        }
        return array_merge($columns, self::$buttonGroups[$groupName], self::$defaultColumns);
    }

    public static function getFilterLabelOptionPairs($advancedFilters): array
    {
        $filterLabelOptionPairs = [];
        foreach ($advancedFilters as $filters) {
            foreach ($filters as $labelKey => $filter) {
                $filterLabelOptionPairs['labels'][$labelKey] = $filter['label'];
                foreach ($filter['options'] as $optionKey => $filterOption) {
                    $filterLabelOptionPairs['options'][$labelKey][$filterOption] = $optionKey;
                }
            }
        }
        $filterLabelOptionPairs['labels'] = array_merge($filterLabelOptionPairs['labels'], self::$filterDateFieldLabels);
        return $filterLabelOptionPairs;
    }

    public static function getParticipantIncentive($participantIncentives): string
    {
        if ($incentiveDateGiven = self::getParticipantIncentiveDateGiven($participantIncentives)) {
            return self::HTML_SUCCESS . ' ' . $incentiveDateGiven;
        }
        return self::HTML_DANGER;
    }

    public static function getParticipantIncentiveDateGiven($participantIncentives): string
    {
        if ($participantIncentives && is_array($participantIncentives)) {
            $count = count($participantIncentives);
            for ($i = $count - 1; $i >= 0; $i--) {
                if ($participantIncentives[$i]->cancelled === false) {
                    $incentive = $participantIncentives[$i];
                    break;
                }
            }
            if (!empty($incentive)) {
                $incentiveDate = date_parse($incentive->dateGiven);
                return $incentiveDate['month'] . '/' . $incentiveDate['day'] . '/' .
                    $incentiveDate['year'];
            }
        }
        return '';
    }

    public static function displayDateStatus($time, $userTimezone, $displayTime = true): string
    {
        if (!empty($time)) {
            return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $userTimezone, $displayTime);
        }
        return self::HTML_DANGER;
    }

    public static function getNphStudyStatus(Participant $participant, string $userTimezone, bool $displayTime = false): string
    {
        if ($participant->nphWithdrawal) {
            return self::HTML_DANGER . ' ' . self::dateFromString($participant->nphWithdrawalAuthored, $userTimezone, $displayTime) . ' (Withdrawn)';
        } elseif ($participant->nphDeactivation) {
            return self::HTML_DANGER . ' ' . self::dateFromString($participant->nphDeactivationAuthored, $userTimezone, $displayTime) . ' (Deactivated)';
        } elseif ($participant->consentForNphModule1) {
            return self::HTML_SUCCESS . ' ' . self::dateFromString($participant->consentForNphModule1Authored, $userTimezone, $displayTime) . ' Module 1 (Consented)';
        }
        return self::HTML_DANGER . ' (Not Consented)';
    }

    public static function getCsvNphStudyStatus(Participant $participant, string $fieldKey, string $userTimezone): int|string
    {
        if (str_contains($fieldKey, 'Authored')) {
            return self::dateFromString($participant->$fieldKey, $userTimezone);
        }

        return $participant->$fieldKey ? 1 : 0;
    }

    public static function getPediatricStatus(bool $isPediatric): string
    {
        return $isPediatric ? 'Pediatric Participant' : 'Adult Participant';
    }

    public static function getRelatedParticipants(string|array|null $relatedParticipants): string
    {
        if (!is_array($relatedParticipants)) {
            return 'N/A';
        }
        $participantIds = array_map(function ($participant) {
            return $participant->participantId;
        }, $relatedParticipants);

        return implode(', ', $participantIds);
    }

    public static function getPediatricAdultString(string $displayNa, bool $isPediatric): string
    {
        return ($displayNa === self::NA_PEDIATRIC && $isPediatric) || ($displayNa === self::NA_ADULT && !$isPediatric) ? 'N/A' : '';
    }

    public static function getParticipantSummarySamples(bool $isPediatric): array
    {
        return $isPediatric ? self::$pedsSamples : array_diff_key(self::$samples, array_flip(self::$pedsOnlyFields));
    }

    public static function getParticipantSummarySurveys(bool $isPediatric): array
    {
        return $isPediatric ? self::$pedsSurveys : array_diff_key(self::$surveys, array_flip(self::$pedsOnlyFields));
    }
}
