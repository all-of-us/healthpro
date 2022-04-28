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
            'name' => 'Last Name',
            'rdrField' => 'lastName',
            'sortField' => 'lastName',
            'generateLink' => true,
            'toggleColumn' => false,
            'default' => true
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
            'sortField' => 'firstName',
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
            'rdrField' => 'enrollmentStatus',
            'sortField' => 'enrollmentStatus',
            'toggleColumn' => true,
            'type' => 'participantStatus',
            'group' => 'details',
            'default' => true
        ],
        'coreParticipant' => [
            'name' => 'Core Participant Date',
            'rdrDateField' => 'enrollmentStatusCoreStoredSampleTime',
            'sortField' => 'enrollmentStatus',
            'toggleColumn' => true
        ],
        'enrollmentStatusCoreMinusPMTime' => [
            'name' => 'Core Participant Minus PM Date',
            'rdrDateField' => 'enrollmentStatusCoreMinusPMTime',
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
            'name' => 'First Primary Consent',
            'csvName' => 'Date of First Primary Consent',
            'rdrField' => 'consentForStudyEnrollmentFirstYesAuthored',
            'sortField' => 'consentForStudyEnrollmentFirstYesAuthored',
            'method' => 'displayFirstConsentStatusTime',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'userTimezone' => true,
            'visible' => false,
            'csvFormatDate' => true,
            'group' => 'consent'
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
            'method' => 'displayConsentStatus',
            'params' => 5,
            'displayTime' => true,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'pdfPath' => 'consentForStudyEnrollmentFilePath',
            'group' => 'consent',
            'default' => true
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
            'default' => true
        ],
        'firstEhrConsent' => [
            'name' => 'First EHR Consent',
            'csvName' => 'Date of First EHR Consent',
            'rdrField' => 'consentForElectronicHealthRecordsFirstYesAuthored',
            'sortField' => 'consentForElectronicHealthRecordsFirstYesAuthored',
            'method' => 'displayFirstConsentStatusTime',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'firstEhrConsent',
            'visible' => false,
            'csvFormatDate' => true,
            'group' => 'consent',
            'default' => true
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
            'method' => 'displayConsentStatus',
            'params' => 5,
            'displayTime' => true,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'pdfPath' => 'consentForElectronicHealthRecordsFilePath',
            'group' => 'consent',
            'default' => true
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
            'default' => true
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
            'group' => 'consent'
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
            'group' => 'metrics'
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
            'group' => 'metrics'
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
            'group' => 'contact'
        ],
        'phone' => [
            'name' => 'Contact Phone',
            'csvName' => 'Phone',
            'rdrField' => 'phoneNumber',
            'sortField' => 'phoneNumber',
            'toggleColumn' => true,
            'visible' => false,
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
            'group' => 'surveys'
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
            'group' => 'surveys'
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
            'group' => 'surveys'
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
            'group' => 'surveys'
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
            'group' => 'surveys'
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
            'group' => 'surveys'
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
            'group' => 'surveys'
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
            'group' => 'surveys'
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
            'group' => 'surveys'
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
            'group' => 'surveys'
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
            'group' => 'surveys'
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
            'group' => 'surveys'
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
            'group' => 'surveys'
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
            'group' => 'surveys'
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
            'group' => 'surveys'
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
            'group' => 'surveys'
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
        'physicalMeasurementsStatus' => [
            'name' => 'Phys Measurements',
            'csvNames' => [
                'Physical Measurements Status',
                'Physical Measurements Completion Date'
            ],
            'rdrField' => 'physicalMeasurementsStatus',
            'sortField' => 'physicalMeasurementsStatus',
            'rdrDateField' => 'physicalMeasurementsFinalizedTime',
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
            'sortField' => 'evaluationFinalizedSite',
            'serviceMethod' => 'getSiteDisplayName',
            'toggleColumn' => true,
            'visible' => false,
            'group' => 'enrollment'
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
            'group' => 'enrollment'
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
            'group' => 'enrollment'
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
            'group' => 'enrollment'
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
            'group' => 'enrollment'
        ],
        '1ED02' => [
            'name' => '2 mL EDTA',
            'csvNames' => [
                '2 mL EDTA Received',
                '2 mL EDTA Received Date'
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
        '1ED04' => [
            'name' => '4 mL EDTA',
            'csvNames' => [
                '4 mL EDTA Received',
                '4 mL EDTA Received Date'
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
            'group' => 'enrollment'
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
            'group' => 'enrollment'
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
            'group' => 'enrollment'
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
            'group' => 'demographics'
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
        'digitalHealthSharingStatus',
        'retentionEligibleStatus',
        'retentionType',
        'isEhrDataAvailable',
        'latestEhrReceiptTime',
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
        'pairedSite',
        'pairedOrganization',
        'physicalMeasurementsStatus',
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
        'age',
        'sex',
        'genderIdentity',
        'race',
        'education',
    ];

    public static $columnGroups = [
        'details' => 'Participant Details',
        'consent' => 'Consent',
        'metrics' => 'Metrics',
        'status' => 'Patient Status',
        'contact' => 'Contact',
        'surveys' => 'PPI Surveys',
        'enrollment' => 'In Person Enrollment',
        'demographics' => 'Demographics'
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
        'primaryLanguage'
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
        'CopeMay',
        'CopeJune',
        'CopeJuly',
        'CopeNov',
        'CopeDec',
        'pairedSite',
        'pairedOrganization',
        'physicalMeasurementsStatus',
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
        'enrollmentStatusCoreMinusPMTime',
        'CopeVaccineMinute1',
        'CopeVaccineMinute2',
        'fitbit',
        'appleHealthKit',
        'appleEHR',
        'PersonalAndFamilyHealthHistory',
        'SocialDeterminantsOfHealth',
        'CopeVaccineMinute3',
        'CopeVaccineMinute4'
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
        'streetAddress2',
        'city',
        'stateId',
        'zipCode',
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
        'questionnaireOnPersonalAndFamilyHealthHistoryAuthored',
        'questionnaireOnHealthcareAccessAuthored',
        'questionnaireOnSocialDeterminantsOfHealthAuthored',
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

    //TODO rename to advancedFilters
    public static $consentAdvanceFilters = [
        'Status' => [
            'enrollmentStatus' => [
                'label' => 'Participant Status',
                'options' => [
                    'View All' => '',
                    'Participant' => 'INTERESTED',
                    'Participant + EHR Consent' => 'MEMBER',
                    'Core Participant' => 'FULL_PARTICIPANT',
                    'Core Participant Minus PM' => 'CORE_MINUS_PM'
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
        ]
    ];

    public static $filterIcons = [
        'Status' => 'fa-user-check',
        'Consents' => 'fa-file-contract',
        'Demographics' => 'fa-globe',
        'EHR' => 'fa-laptop-medical',
        'Retention' => 'fa-check-double',
        'Pairing' => 'fa-building'
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

    public static $buttonGroups = [
        'default' => [
            'dateOfBirth',
            'participantId',
            'participantStatus',
            'activityStatus',
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
            'physicalMeasurementsStatus',
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
            'latestEhrReceiptTime'
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
        'participantStatus' => [
            'participantStatus',
            'coreParticipant',
            'enrollmentStatusCoreMinusPMTime'
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

    public static function dateFromString($string, $timezone = null, $displayTime = true, $link = null)
    {
        if (!empty($string)) {
            try {
                if ($timezone) {
                    $date = new \DateTime($string);
                    $date->setTimezone(new \DateTimeZone($timezone));
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

    public static function displayConsentStatus($value, $time, $userTimezone, $displayTime = true, $link = null)
    {
        switch ($value) {
            case 'SUBMITTED':
                return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link) . ' (Consented Yes)';
            case 'SUBMITTED_NO_CONSENT':
                return self::HTML_DANGER . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link) . ' (Refused Consent)';
            case 'SUBMITTED_NOT_SURE':
                return self::HTML_WARNING . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link) . ' (Responded Not Sure)';
            case 'SUBMITTED_INVALID':
                return self::HTML_DANGER . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link) . ' (Invalid)';
            default:
                return self::HTML_DANGER . ' (Consent Not Completed)';
        }
    }

    public static function displayGenomicsConsentStatus($value, $time, $userTimezone, $displayTime = true, $link = null)
    {
        switch ($value) {
            // Note the icons differ from ::displayConsentStatus
            case 'SUBMITTED':
                return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link) . ' (Consented Yes)';
            case 'SUBMITTED_NO_CONSENT':
                return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link) . ' (Refused Consent)';
            case 'SUBMITTED_NOT_SURE':
                return self::HTML_SUCCESS . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link) . ' (Responded Not Sure)';
            case 'SUBMITTED_INVALID':
                return self::HTML_DANGER . ' ' . self::dateFromString($time, $userTimezone, $displayTime, $link) . ' (Invalid)';
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

    public static function getDigitalHealthSharingStatus($digitalHealthSharingStatus, $type, $userTimezone)
    {
        if ($digitalHealthSharingStatus) {
            if (isset($digitalHealthSharingStatus->{$type}->status)) {
                /** @phpstan-ignore-next-line */
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

    public static function isValidDate($date)
    {
        $dt = \DateTime::createFromFormat("m/d/Y", $date);
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
}
