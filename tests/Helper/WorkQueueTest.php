<?php

namespace App\Tests\Helper;

use App\Helper\WorkQueue;
use PHPUnit\Framework\TestCase;

class WorkQueueTest extends TestCase
{
    public function testConsentExportHeaders()
    {
        $exportHeaders = WorkQueue::getConsentExportHeaders(WorkQueue::getWorkQueueConsentColumns());
        self::assertSame([
            'Last Name',
            'First Name',
            'Middle Initial',
            'Date of Birth',
            'PMI ID',
            'Primary Consent Status',
            'Primary Consent Date',
            'Program Update',
            'Date of Program Update',
            'EHR Consent Status',
            'EHR Consent Date',
            'EHR Expiration Status',
            'EHR Expiration Date',
            'gRoR Consent Status',
            'gRoR Consent Date',
            'DV-only EHR Sharing',
            'DV-only EHR Sharing Date',
            'CABoR Consent Status',
            'CABoR Consent Date',
            'Fitbit Consent',
            'Fitbit Consent Date',
            'Apple HealthKit Consent',
            'Apple HealthKit Consent Date',
            'Apple EHR Consent',
            'Apple EHR Consent Date',
            'Consent Cohort',
            'Language of Primary Consent'
        ], $exportHeaders);
    }

    public function testDefaultGroupExportSelectedHeaders()
    {
        $exportHeaders = WorkQueue::getSessionExportHeaders(WorkQueue::getWorkQueueColumns());
        self::assertSame([
            'Last Name',
            'First Name',
            'Middle Initial',
            'Date of Birth',
            'PMI ID',
            'Participant Status',
            'Core Participant Date',
            'Withdrawal Status',
            'Withdrawal Date',
            'Deactivation Status',
            'Deactivation Date',
            'Deceased',
            'Date of Death',
            'Date of Death Approval',
            'Consent Cohort',
            'Primary Consent Status',
            'Primary Consent Date',
            'Program Update',
            'Date of Program Update',
            'EHR Consent Status',
            'EHR Consent Date',
            'gRoR Consent Status',
            'gRoR Consent Date',
            'Language of Primary Consent',
            'Required PPI Surveys Complete',
            'Completed Surveys',
            'Paired Site',
            'Paired Organization',
            'Physical Measurements Status',
            'Physical Measurements Completion Date',
            'Samples to Isolate DNA',
            'Baseline Samples',
            'Core Participant Minus PM Date'
        ], $exportHeaders);
    }

    public function testContactGroupExportSelectedHeaders()
    {
        $exportHeaders = WorkQueue::getSessionExportHeaders(WorkQueue::getWorkQueueGroupColumns('contact'));
        self::assertSame([
            'Last Name',
            'First Name',
            'Middle Initial',
            'PMI ID',
            'Retention Eligible',
            'Date of Retention Eligibility',
            'Retention Status',
            'Street Address',
            'Street Address2',
            'City',
            'State',
            'Zip',
            'Email',
            'Login Phone',
            'Phone'
        ], $exportHeaders);
    }

    public function testDemographicsGroupExportSelectedHeaders()
    {
        $exportHeaders = WorkQueue::getSessionExportHeaders(WorkQueue::getWorkQueueGroupColumns('demographics'));
        self::assertSame([
            'Last Name',
            'First Name',
            'Middle Initial',
            'Date of Birth',
            'Participant Status',
            'Core Participant Date',
            'Sex',
            'Gender Identity',
            'Race/Ethnicity',
            'Education',
            'Core Participant Minus PM Date'
        ], $exportHeaders);
    }

    public function testPatientStatusGroupExportSelectedHeaders()
    {
        $exportHeaders = WorkQueue::getSessionExportHeaders(WorkQueue::getWorkQueueGroupColumns('status'));
        self::assertSame([
            'Last Name',
            'First Name',
            'Middle Initial',
            'Date of Birth',
            'PMI ID',
            'Participant Status',
            'Core Participant Date',
            'Withdrawal Status',
            'Withdrawal Date',
            'Deactivation Status',
            'Deactivation Date',
            'Deceased',
            'Date of Death',
            'Date of Death Approval',
            'Consent Cohort',
            'Primary Consent Status',
            'Primary Consent Date',
            'Program Update',
            'Date of Program Update',
            'EHR Consent Status',
            'EHR Consent Date',
            'EHR Expiration Status',
            'EHR Expiration Date',
            'gRoR Consent Status',
            'gRoR Consent Date',
            'Language of Primary Consent',
            'EHR Data Transfer',
            'Most Recent EHR Receipt',
            'Patient Status: Yes',
            'Patient Status: No',
            'Patient Status: No Access',
            'Patient Status: Unknown',
            'Core Participant Minus PM Date'
        ], $exportHeaders);
    }

    public function testGetFilterLabelOptionPairs()
    {
        $filterLabelOptionPairs = [
            'labels' => [
                'enrollmentStatus' => 'Participant Status',
                'activityStatus' => 'Activity Status',
                'patientStatus' => 'Patient Status',
                'consentForStudyEnrollment' => 'Primary Consent',
                'questionnaireOnDnaProgram' => 'Program Update',
                'consentForElectronicHealthRecords' => 'EHR Consent Status',
                'consentForGenomicsROR' => 'gRoR Consent Status',
                'consentForDvElectronicHealthRecordsSharing' => 'DV-Only EHR Sharing',
                'consentForCABoR' => 'CABoR Consent',
                'consentCohort' => 'Consent Cohort',
                'primaryLanguage' => 'Language of Primary Consent',
                'ageRange' => 'Age',
                'race' => 'Race',
                'genderIdentity' => 'Gender Identity',
                'isEhrDataAvailable' => 'EHR Data Transfer',
                'ehrConsentExpireStatus' => 'EHR Expiration Status',
                'retentionType' => 'Retention Status',
                'retentionEligibleStatus' => 'Retention Eligible',
                'participantOrigin' => 'Participant Origination',
                'enrollmentSite' => 'Enrollment Site'
            ],
            'options' =>
                [
                    'enrollmentStatus' =>
                        [
                            '' => 'View All',
                            'INTERESTED' => 'Participant',
                            'MEMBER' => 'Participant + EHR Consent',
                            'FULL_PARTICIPANT' => 'Core Participant',
                            'CORE_MINUS_PM' => 'Core Participant Minus PM',
                        ],
                    'activityStatus' =>
                        [
                            '' => 'View All',
                            'active' => 'Active',
                            'deactivated' => 'Deactivated',
                            'withdrawn' => 'Withdrawn',
                            'not_withdrawn' => 'Not Withdrawn',
                            'deceased' => 'Deceased',
                            'deceased_pending' => 'Deceased (Pending)',
                        ],
                    'patientStatus' =>
                        [
                            '' => 'View All',
                            'YES' => 'Yes',
                            'NO' => 'No',
                            'NO_ACCESS' => 'No Access',
                            'UNKNOWN' => 'Unknown',
                            'UNSET' => 'Not Completed',
                        ],
                    'consentForStudyEnrollment' =>
                        [
                            '' => 'View All',
                            'SUBMITTED' => 'Consented',
                            'SUBMITTED_NO_CONSENT' => 'Refused Consent',
                            'UNSET' => 'Consent Not Completed',
                        ],
                    'questionnaireOnDnaProgram' =>
                        [
                            '' => 'View All',
                            'SUBMITTED' => 'Completed',
                            'UNSET' => 'Not Completed',
                        ],
                    'consentForElectronicHealthRecords' =>
                        [
                            '' => 'View All',
                            'SUBMITTED' => 'Consented',
                            'SUBMITTED_NO_CONSENT' => 'Refused consent',
                            'UNSET' => 'Consent not completed',
                        ],
                    'consentForGenomicsROR' =>
                        [
                            '' => 'View All',
                            'SUBMITTED' => 'Consented Yes',
                            'SUBMITTED_NO_CONSENT' => 'Refused Consent',
                            'SUBMITTED_NOT_SURE' => 'Responded Not Sure',
                            'UNSET' => 'Consent Not Completed',
                        ],
                    'consentForDvElectronicHealthRecordsSharing' =>
                        [
                            '' => 'View All',
                            'SUBMITTED' => 'Consented Yes',
                            'SUBMITTED_NO_CONSENT' => 'Refused Consent',
                            'SUBMITTED_NOT_SURE' => 'Responded Not Sure',
                            'UNSET' => 'Consent Not Completed',
                        ],
                    'consentForCABoR' =>
                        [
                            '' => 'View All',
                            'SUBMITTED' => 'Consented Yes',
                            'SUBMITTED_NO_CONSENT' => 'Refused Consent',
                            'SUBMITTED_NOT_SURE' => 'Responded Not Sure',
                            'UNSET' => 'Consent Not Completed',
                        ],
                    'consentCohort' =>
                        [
                            '' => 'View All',
                            'COHORT_1' => 'Cohort 1',
                            'COHORT_2' => 'Cohort 2',
                            'COHORT_2_PILOT' => 'Cohort 2 Pilot',
                            'COHORT_3' => 'Cohort 3',
                        ],
                    'primaryLanguage' =>
                        [
                            '' => 'View All',
                            'en' => 'English',
                            'es' => 'Spanish',
                        ],
                    'ageRange' =>
                        [
                            '' => 'View All',
                            '0-17' => '0-17',
                            '18-25' => '18-25',
                            '26-35' => '26-35',
                            '36-45' => '36-45',
                            '46-55' => '46-55',
                            '56-65' => '56-65',
                            '66-75' => '66-75',
                            '76-85' => '76-85',
                            '86-' => '86+',
                        ],
                    'race' =>
                        [
                            '' => 'View All',
                            'AMERICAN_INDIAN_OR_ALASKA_NATIVE' => 'American Indian / Alaska Native',
                            'BLACK_OR_AFRICAN_AMERICAN' => 'Black or African American',
                            'ASIAN' => 'Asian',
                            'NATIVE_HAWAIIAN_OR_OTHER_PACIFIC_ISLANDER' => 'Native Hawaiian or Other Pacific Islander',
                            'WHITE' => 'White',
                            'HISPANIC_LATINO_OR_SPANISH' => 'Hispanic, Latino, or Spanish',
                            'MIDDLE_EASTERN_OR_NORTH_AFRICAN' => 'Middle Eastern or North African',
                            'HLS_AND_WHITE' => 'H/L/S and White',
                            'HLS_AND_BLACK' => 'H/L/S and Black',
                            'HLS_AND_ONE_OTHER_RACE' => 'H/L/S and one other race',
                            'HLS_AND_MORE_THAN_ONE_OTHER_RACE' => 'H/L/S and more than one other race',
                            'MORE_THAN_ONE_RACE' => 'More than one race',
                            'OTHER_RACE' => 'Other',
                        ],
                    'genderIdentity' =>
                        [
                            '' => 'View All',
                            'GenderIdentity_Man' => 'Man',
                            'GenderIdentity_Woman' => 'Woman',
                            'GenderIdentity_NonBinary' => 'Non-binary',
                            'GenderIdentity_Transgender' => 'Transgender',
                            'GenderIdentity_MoreThanOne' => 'More Than One Gender Identity',
                            'GenderIdentity_AdditionalOptions' => 'Other',
                        ],
                    'isEhrDataAvailable' =>
                        [
                            '' => 'View All',
                            'yes' => 'Yes',
                            'no' => 'No',
                        ],
                    'ehrConsentExpireStatus' =>
                        [
                            '' => 'View All',
                            'ACTIVE' => 'Active',
                            'EXPIRED' => 'Expired',
                        ],
                    'retentionType' =>
                        [
                            '' => 'View All',
                            'ACTIVE' => 'Active Only',
                            'PASSIVE' => 'Passive Only',
                            'ACTIVE_AND_PASSIVE' => 'Active and Passive',
                            'UNSET' => 'Not Retained',
                        ],
                    'retentionEligibleStatus' =>
                        [
                            '' => 'View All',
                            'ELIGIBLE' => 'Yes',
                            'NOT_ELIGIBLE' => 'No',
                        ],
                    'participantOrigin' =>
                        [
                            '' => 'View All',
                            'vibrent' => 'PTSC Portal',
                            'careevolution' => 'DV Pilot Portal',
                        ],
                    'enrollmentSite' =>
                        [
                            '' => 'View All',
                            'UNSET' => 'Unpaired',
                        ],
                ]
        ];
        $filterLabelOptionPairs['labels'] = array_merge($filterLabelOptionPairs['labels'],
            WorkQueue::$filterDateFieldLabels);
        $advancedFilters = WorkQueue::$consentAdvanceFilters;
        $this->assertSame($filterLabelOptionPairs, WorkQueue::getFilterLabelOptionPairs($advancedFilters));
    }
}
