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
        'biobankId' => [
            'displayName' => 'Biobank ID',
            'csvLabel' => 'Biobank ID',
            'rdrField' => 'biobankId',
            'sortField' => 'biobankId',
            'toggleColumn' => true,
            'visible' => false
        ],
        'participantStatus' => [
            'displayName' => 'Participant Status',
            'csvLabel' => 'Participant Status',
            'rdrField' => 'enrollmentStatus',
            'sortField' => 'enrollmentStatus',
            'toggleColumn' => true,
            'type' => 'participantStatus'
        ],
        'coreParticipant' => [
            'displayName' => 'Core Participant Date',
            'csvLabel' => 'Core Participant Date',
            'rdrDateField' => 'enrollmentStatusCoreStoredSampleTime',
            'sortField' => 'enrollmentStatus',
            'toggleColumn' => true
        ],
        'enrollmentStatusCoreMinusPMTime' => [
            'displayName' => 'Core Participant Minus PM Date',
            'csvLabel' => 'Core Participant Minus PM Date',
            'rdrDateField' => 'enrollmentStatusCoreMinusPMTime',
        ],
        'activityStatus' => [
            'displayName' => 'Activity Status',
            'csvLabel' => 'Activity Status',
            'rdrField' => 'activityStatus',
            'sortField' => 'activityStatus',
            'method' => 'getActivityStatus',
            'toggleColumn' => true,
            'type' => 'activityStatus',
            'visible' => false
        ],
        'withdrawalStatus' => [
            'displayName' => 'Withdrawal Status',
            'csvLabels' => [
                'Withdrawal Status',
                'Withdrawal Date'
            ],
            'rdrField' => 'isWithdrawn',
            'rdrDateField' => 'withdrawalAuthored',
            'fieldCheck' => true
        ],
        'withdrawalReason' => [
            'displayName' => 'Withdrawal Reason',
            'csvLabel' => 'Withdrawal Reason',
            'rdrField' => 'withdrawalReason',
            'sortField' => 'withdrawalReason',
            'toggleColumn' => true,
            'visible' => false
        ],
        'deactivationStatus' => [
            'displayName' => 'Deactivation Status',
            'csvLabels' => [
                'Deactivation Status',
                'Deactivation Date'
            ],
            'rdrField' => 'isSuspended',
            'rdrDateField' => 'suspensionTime',
            'fieldCheck' => true
        ],
        'deceasedStatus' => [
            'displayName' => 'Deceased',
            'csvLabel' => 'Deceased',
            'rdrField' => 'deceasedStatus',
            'csvMethod' => 'csvDeceasedStatus'
        ],
        'dateOfdeath' => [
            'displayName' => 'Date of Death',
            'csvLabel' => 'Date of Death',
            'rdrDateField' => 'dateOfdeath',
        ],
        'dateOfdeathApproval' => [
            'displayName' => 'Date of Death Approval',
            'csvLabel' => 'Date of Death Approval',
            'rdrDateField' => 'deceasedAuthored',
            'csvStatusText' => 'APPROVED'
        ],
        'participantOrigin' => [
            'displayName' => 'Participant Origination',
            'csvLabel' => 'Participant Origination',
            'rdrField' => 'participantOrigin',
            'sortField' => 'participantOrigin',
            'toggleColumn' => true,
            'checkDvVisibility' => true
        ],
        'consentCohort' => [
            'displayName' => 'Consent Cohort',
            'csvLabel' => 'Consent Cohort',
            'rdrField' => 'consentCohortText',
            'sortField' => 'consentCohort',
            'htmlClass' => 'text-center',
            'toggleColumn' => true
        ],
        'firstPrimaryConsent' => [
            'displayName' => 'First Primary Consent',
            'csvLabel' => 'Date of First Primary Consent',
            'rdrField' => 'consentForStudyEnrollmentFirstYesAuthored',
            'sortField' => 'consentForStudyEnrollmentFirstYesAuthored',
            'method' => 'displayFirstConsentStatusTime',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'userTimezone' => true,
            'visible' => false
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
            'params' => 5,
            'displayTime' => true,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'pdfPath' => 'consentForStudyEnrollmentFilePath'
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
        'firstEhrConsent' => [
            'displayName' => 'First EHR Consent',
            'csvLabel' => 'Date of First EHR Consent',
            'rdrField' => 'consentForStudyEnrollmentFirstYesAuthored',
            'sortField' => 'consentForStudyEnrollmentFirstYesAuthored',
            'method' => 'displayFirstConsentStatusTime',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'firstEhrConsent',
            'visible' => false
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
            'params' => 5,
            'displayTime' => true,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'pdfPath' => 'consentForElectronicHealthRecordsFilePath'
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
            'params' => 4,
            'displayTime' => false,
            'csvMethod' => 'csvEhrConsentExpireStatus',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false
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
            'params' => 5,
            'displayTime' => true,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'pdfPath' => 'consentForGenomicsRORFilePath'
        ],
        'primaryLanguage' => [
            'displayName' => 'Language of Primary Consent',
            'csvLabel' => 'Language of Primary Consent',
            'rdrField' => 'primaryLanguage',
            'sortField' => 'primaryLanguage',
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
            'params' => 5,
            'displayTime' => false,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false
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
            'params' => 5,
            'displayTime' => true,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'pdfPath' => 'consentForCABoRFilePath',
            'visible' => false
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
            'toggleColumn' => true,
            'visible' => false
        ],
        'retentionEligibleStatus' => [
            'displayName' => 'Retention Eligible',
            'csvLabels' => [
                'Retention Eligible',
                'Date of Retention Eligible'
            ],
            'rdrField' => 'retentionEligibleStatus',
            'sortField' => 'retentionEligibleStatus',
            'rdrDateField' => 'retentionEligibleTime',
            'method' => 'getRetentionEligibleStatus',
            'params' => 3,
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'csvStatusText' => 'ELIGIBLE',
            'visible' => false
        ],
        'retentionType' => [
            'displayName' => 'Retention Status',
            'csvLabel' => 'Retention Status',
            'rdrField' => 'retentionType',
            'sortField' => 'retentionType',
            'method' => 'getRetentionType',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false
        ],
        'isEhrDataAvailable' => [
            'displayName' => 'EHR Data Transfer',
            'csvLabel' => 'EHR Data Transfer',
            'rdrField' => 'isEhrDataAvailable',
            'sortField' => 'isEhrDataAvailable',
            'method' => 'getEhrAvailableStatus',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'fieldCheck' => true,
            'visible' => false
        ],
        'latestEhrReceiptTime' => [
            'displayName' => 'Most Recent EHR Receipt',
            'csvLabel' => 'Most Recent EHR Receipt',
            'rdrField' => 'latestEhrReceiptTime',
            'sortField' => 'latestEhrReceiptTime',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'userTimezone' => true,
            'visible' => false
        ],
        'patientStatusYes' => [
            'displayName' => 'Yes',
            'method' => 'getPatientStatus',
            'type' => 'patientStatus',
            'value' => 'YES',
            'visible' => false
        ],
        'patientStatusNo' => [
            'displayName' => 'No',
            'method' => 'getPatientStatus',
            'type' => 'patientStatus',
            'value' => 'NO',
            'visible' => false
        ],
        'patientStatusNoAccess' => [
            'displayName' => 'No Access',
            'method' => 'getPatientStatus',
            'type' => 'patientStatus',
            'value' => 'NO_ACCESS',
            'visible' => false
        ],
        'patientStatusUnknown' => [
            'displayName' => 'Unknown',
            'method' => 'getPatientStatus',
            'type' => 'patientStatus',
            'value' => 'UNKNOWN',
            'visible' => false
        ],
        'contactMethod' => [
            'displayName' => 'Contact Method',
            'csvLabel' => 'Contact Method',
            'rdrField' => 'recontactMethod',
            'sortField' => 'recontactMethod',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false
        ],
        'address' => [
            'displayName' => 'Address',
            'csvLabel' => 'Street Address',
            'rdrField' => 'address',
            'sortField' => 'address',
            'participantMethod' => 'getAddress',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'address',
            'csvRdrField' => 'streetAddress',
            'visible' => false
        ],
        'address2' => [
            'displayName' => 'Address2',
            'csvLabel' => 'Street Address2',
            'csvRdrField' => 'streetAddress2'
        ],
        'city' => [
            'displayName' => 'City',
            'csvLabel' => 'City',
            'csvRdrField' => 'city'
        ],
        'state' => [
            'displayName' => 'State',
            'csvLabel' => 'State',
            'csvRdrField' => 'state'
        ],
        'zip' => [
            'displayName' => 'Zip',
            'csvLabel' => 'Zip',
            'csvRdrField' => 'zip'
        ],
        'email' => [
            'displayName' => 'Email',
            'csvLabel' => 'Email',
            'rdrField' => 'email',
            'sortField' => 'email',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false
        ],
        'loginPhone' => [
            'displayName' => 'Login Phone',
            'csvLabel' => 'Login Phone',
            'rdrField' => 'loginPhoneNumber',
            'sortField' => 'loginPhoneNumber',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false
        ],
        'phone' => [
            'displayName' => 'Contact Phone',
            'csvLabel' => 'Contact Phone',
            'rdrField' => 'phoneNumber',
            'sortField' => 'phoneNumber',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false
        ],
        'ppiStatus' => [
            'displayName' => 'Required Complete',
            'csvLabel' => 'Required PPI Surveys Complete',
            'rdrField' => 'numCompletedBaselinePPIModules',
            'sortField' => 'numCompletedBaselinePPIModules',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'ppiStatus',
            'csvStatusText' => 3
        ],
        'ppiSurveys' => [
            'displayName' => 'Completed Surveys',
            'csvLabel' => 'Completed Surveys',
            'rdrField' => 'numCompletedPPIModules',
            'sortField' => 'numCompletedPPIModules',
            'htmlClass' => 'text-center',
            'toggleColumn' => true
        ],
        'TheBasics' => [
            'displayName' => 'Basics',
            'csvLabels' => [
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
            'visible' => false
        ],
        'OverallHealth' => [
            'displayName' => 'Health',
            'csvLabels' => [
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
            'visible' => false
        ],
        'Lifestyle' => [
            'displayName' => 'Lifestyle',
            'csvLabels' => [
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
            'visible' => false
        ],
        'MedicalHistory' => [
            'displayName' => 'Med History',
            'csvLabels' => [
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
            'visible' => false
        ],
        'FamilyHealth' => [
            'displayName' => 'Family History',
            'csvLabels' => [
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
            'visible' => false
        ],
        'PersonalAndFamilyHealthHistory' => [
            'displayName' => 'Personal & Family Hx',
            'csvLabels' => [
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
            'visible' => false
        ],
        'HealthcareAccess' => [
            'displayName' => 'Access',
            'csvLabels' => [
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
            'visible' => false
        ],
        'SocialDeterminantsOfHealth' => [
            'displayName' => 'SDOH',
            'csvLabels' => [
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
            'visible' => false
        ],
        'CopeMay' => [
            'displayName' => 'COPE May',
            'csvLabels' => [
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
            'visible' => false
        ],
        'CopeJune' => [
            'displayName' => 'COPE June',
            'csvLabels' => [
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
            'visible' => false
        ],
        'CopeJuly' => [
            'displayName' => 'COPE July',
            'csvLabels' => [
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
            'visible' => false
        ],
        'CopeNov' => [
            'displayName' => 'COPE Nov',
            'csvLabels' => [
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
            'visible' => false
        ],
        'CopeDec' => [
            'displayName' => 'COPE Dec',
            'csvLabels' => [
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
            'visible' => false
        ],
        'CopeFeb' => [
            'displayName' => 'COPE Feb',
            'csvLabels' => [
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
            'visible' => false
        ],
        'CopeVaccineMinute1' => [
            'displayName' => 'Summer Minute',
            'csvLabels' => [
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
            'visible' => false
        ],
        'CopeVaccineMinute2' => [
            'displayName' => 'Fall Minute',
            'csvLabels' => [
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
            'visible' => false
        ],
        'CopeVaccineMinute3' => [
            'displayName' => 'Winter Minute',
            'csvLabels' => [
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
            'visible' => false
        ],
        'pairedSite' => [
            'displayName' => 'Paired Site',
            'csvLabel' => 'Paired Site',
            'rdrField' => 'siteSuffix',
            'sortField' => 'siteSuffix',
            'serviceMethod' => 'getSiteDisplayName',
            'htmlClass' => 'text-center',
            'toggleColumn' => true
        ],
        'pairedOrganization' => [
            'displayName' => 'Paired Organization',
            'csvLabel' => 'Paired Organization',
            'rdrField' => 'organization',
            'sortField' => 'organization',
            'serviceMethod' => 'getOrganizationDisplayName',
            'htmlClass' => 'text-center',
            'toggleColumn' => true
        ],
        'physicalMeasurementsStatus' => [
            'displayName' => 'Phys Measurements',
            'csvLabels' => [
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
            'csvStatusText' => 'COMPLETED'
        ],
        'evaluationFinalizedSite' => [
            'displayName' => 'Phys Meas Site',
            'csvLabel' => 'Physical Measurements Site',
            'rdrField' => 'evaluationFinalizedSite',
            'sortField' => 'evaluationFinalizedSite',
            'serviceMethod' => 'getSiteDisplayName',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false
        ],
        'biobankDnaStatus' => [
            'displayName' => 'Samples to Isolate DNA?',
            'csvLabel' => 'Samples to Isolate DNA',
            'rdrField' => 'samplesToIsolateDNA',
            'sortField' => 'samplesToIsolateDNA',
            'method' => 'displayStatus',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'statusText' => 'RECEIVED',
            'csvStatusText' => 'RECEIVED'
        ],
        'biobankSamples' => [
            'displayName' => 'Baseline Samples',
            'csvLabel' => 'Baseline Samples',
            'rdrField' => 'numBaselineSamplesArrived',
            'sortField' => 'numBaselineSamplesArrived',
            'htmlClass' => 'text-center',
            'toggleColumn' => true
        ],
        'orderCreatedSite' => [
            'displayName' => 'Bio-specimens Site',
            'csvLabel' => 'Biospecimens Site',
            'rdrField' => 'orderCreatedSite',
            'sortField' => 'orderCreatedSite',
            'serviceMethod' => 'getSiteDisplayName',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false
        ],
        '1SST8' => [
            'displayName' => '8 mL SST',
            'csvLabels' => [
                '8 mL SST Received',
                '8 mL SST Received Date'
            ],
            'rdrField' => 'sampleStatus1SST8',
            'sortField' => 'sampleStatus1SST8Time',
            'rdrDateField' => 'sampleStatus1SST8Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false
        ],
        '1PST8' => [
            'displayName' => '8 mL PST',
            'csvLabels' => [
                '8 mL PST Received',
                '8 mL PST Received Date'
            ],
            'rdrField' => 'sampleStatus1PST8',
            'sortField' => 'sampleStatus1PST8Time',
            'rdrDateField' => 'sampleStatus1PST8Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false
        ],
        '1HEP4' => [
            'displayName' => '4 mL Na-Hep',
            'csvLabels' => [
                '4 mL Na-Hep Received',
                '4 mL Na-Hep Received Date'
            ],
            'rdrField' => 'sampleStatus1HEP4',
            'sortField' => 'sampleStatus1HEP4Time',
            'rdrDateField' => 'sampleStatus1HEP4Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false
        ],
        '1ED02' => [
            'displayName' => '2 mL EDTA',
            'csvLabels' => [
                '2 mL EDTA Received',
                '2 mL EDTA Received Date'
            ],
            'rdrField' => 'sampleStatus1ED02',
            'sortField' => 'sampleStatus1ED02Time',
            'rdrDateField' => 'sampleStatus1ED02Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false
        ],
        '1ED04' => [
            'displayName' => '4 mL EDTA',
            'csvLabels' => [
                '4 mL EDTA Received',
                '4 mL EDTA Received Date'
            ],
            'rdrField' => 'sampleStatus1ED04',
            'sortField' => 'sampleStatus1ED04Time',
            'rdrDateField' => 'sampleStatus1ED04Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false
        ],
        '1ED10' => [
            'displayName' => '1st 10 mL EDTA',
            'csvLabels' => [
                '1st 10 mL EDTA Received',
                '1st 10 mL EDTA Received Date'
            ],
            'rdrField' => 'sampleStatus1ED10',
            'sortField' => 'sampleStatus1ED10Time',
            'rdrDateField' => 'sampleStatus1ED10Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false
        ],
        '2ED10' => [
            'displayName' => '2nd 10 mL EDTA',
            'csvLabels' => [
                '2nd 10 mL EDTA Received',
                '2nd 10 mL EDTA Received Date'
            ],
            'rdrField' => 'sampleStatus2ED10',
            'sortField' => 'sampleStatus2ED10Time',
            'rdrDateField' => 'sampleStatus2ED10Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false
        ],
        '1CFD9' => [
            'displayName' => 'Cell-Free DNA',
            'csvLabels' => [
                'Cell-Free DNA Received',
                'Cell-Free DNA Received Date'
            ],
            'rdrField' => 'sampleStatus1CFD9',
            'sortField' => 'sampleStatus1CFD9Time',
            'rdrDateField' => 'sampleStatus1CFD9Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false
        ],
        '1PXR2' => [
            'displayName' => 'Paxgene RNA',
            'csvLabels' => [
                'Paxgene RNA Received',
                'Paxgene RNA Received Date'
            ],
            'rdrField' => 'sampleStatus1PXR2',
            'sortField' => 'sampleStatus1PXR2Time',
            'rdrDateField' => 'sampleStatus1PXR2Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false
        ],
        '1UR10' => [
            'displayName' => 'Urine 10 mL',
            'csvLabels' => [
                'Urine 10 mL Received',
                'Urine 10 mL Received Date'
            ],
            'rdrField' => 'sampleStatus1UR10',
            'sortField' => 'sampleStatus1UR10Time',
            'rdrDateField' => 'sampleStatus1UR10Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false
        ],
        '1UR90' => [
            'displayName' => 'Urine 90 mL',
            'csvLabels' => [
                'Urine 90 mL Received',
                'Urine 90 mL Received Date'
            ],
            'rdrField' => 'sampleStatus1UR90',
            'sortField' => 'sampleStatus1UR90Time',
            'rdrDateField' => 'sampleStatus1UR90Time',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false
        ],
        '1SAL' => [
            'displayName' => 'Saliva',
            'csvLabels' => [
                'Saliva Received',
                'Saliva Received Date'
            ],
            'rdrField' => 'sampleStatus1SAL',
            'sortField' => 'sampleStatus1SALTime',
            'rdrDateField' => 'sampleStatus1SALTime',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'type' => 'sample',
            'visible' => false
        ],
        'sample1SAL2CollectionMethod' => [
            'displayName' => 'Saliva Collection',
            'csvLabel' => 'Saliva Collection',
            'rdrField' => 'sample1SAL2CollectionMethod'
        ],
        'age' => [
            'displayName' => 'Age',
            'csvLabel' => 'Age',
            'rdrField' => 'age',
            'sortField' => 'age',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false
        ],
        'sex' => [
            'displayName' => 'Sex',
            'csvLabel' => 'Sex',
            'rdrField' => 'sex',
            'sortField' => 'sex',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false
        ],
        'genderIdentity' => [
            'displayName' => 'Gender Identity',
            'csvLabel' => 'Gender Identity',
            'rdrField' => 'genderIdentity',
            'sortField' => 'genderIdentity',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false
        ],
        'race' => [
            'displayName' => 'Race/Ethnicity',
            'csvLabel' => 'Race/Ethnicity',
            'rdrField' => 'race',
            'sortField' => 'race',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false
        ],
        'education' => [
            'displayName' => 'Education',
            'csvLabel' => 'Education',
            'rdrField' => 'education',
            'sortField' => 'education',
            'htmlClass' => 'text-center',
            'toggleColumn' => true,
            'visible' => false
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
        'dateOfdeath',
        'dateOfdeathApproval',
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
        'digitalHealthSharingStatus',
        'PersonalAndFamilyHealthHistory',
        'SocialDeterminantsOfHealth',
        'CopeVaccineMinute3'
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
        'questionnaireOnPersonalAndFamilyHealthHistoryAuthored',
        'questionnaireOnHealthcareAccessAuthored',
        'questionnaireOnSocialDeterminantsOfHealthAuthored',
        'questionnaireOnCopeMayAuthored',
        'questionnaireOnCopeJuneAuthored',
        'questionnaireOnCopeJulyAuthored',
        'questionnaireOnCopeNovAuthored',
        'questionnaireOnCopeDecAuthored',
        'questionnaireOnCopeFebAuthored',
        'questionnaireOnCopeVaccineMinute1',
        'questionnaireOnCopeVaccineMinute2',
        'questionnaireOnCopeVaccineMinute3',
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
        'CopeVaccineMinute3' => 'Winter Minute'
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
        $headers[] = 'Personal & Family Hx PPI Survey Complete';
        $headers[] = 'Personal & Family Hx PPI Survey Completion Date';
        $headers[] = 'SDOH PPI Survey Complete';
        $headers[] = 'SDOH PPI Survey Completion Date';
        $headers[] = 'Winter Minute PPI Survey Complete';
        $headers[] = 'Winter Minute PPI Survey Completion Date';
        return $headers;
    }

    public static function getConsentExportHeaders($sessionConsentColumns)
    {
        $headers = [];
        foreach (self::$consentColumns as $field) {
            $columnDef = self::$columnsDef[$field];
            if ($columnDef['toggleColumn']) {
                if (in_array("column{$field}", $sessionConsentColumns)) {
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
        foreach (self::$consentColumns as $field) {
            $columnDef = self::$columnsDef[$field];
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
}
