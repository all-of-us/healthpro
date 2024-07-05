<?php

namespace App\Tests\Helper;

use App\Helper\WorkQueue;
use App\Tests\Service\ServiceTestCase;
use App\Tests\testSetup;
use Doctrine\ORM\EntityManagerInterface;

class WorkQueueTest extends ServiceTestCase
{
    private testSetup $testSetup;
    public function setUp(): void
    {
        $this->testSetup = new testSetup(static::getContainer()->get(EntityManagerInterface::class));
    }
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
            'Language of Primary Consent',
            'Date of First Primary Consent',
            'Date of First EHR Consent',
            'Date of Primary Re-Consent',
            'Date of EHR Re-Consent',
            'Exploring the Mind Consent Status',
            'Exploring the Mind Consent Date'
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
            'Date of First Primary Consent',
            'Primary Consent Status',
            'Primary Consent Date',
            'Program Update',
            'Date of Program Update',
            'Date of First EHR Consent',
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
            'Core Participant Minus PM Date',
            'Date of Primary Re-Consent',
            'Date of EHR Re-Consent',
            'Enrolled Participant Date',
            'Participant Date',
            'Participant + EHR Date',
            'PM&B Eligible Date',
            'Pediatric Status'
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
            'Phone',
            'Related Participants'
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
            'Core Participant Minus PM Date',
            'Enrolled Participant Date',
            'Participant Date',
            'Participant + EHR Date',
            'PM&B Eligible Date',
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
            'EHR Data Transfer',
            'Most Recent EHR Receipt',
            'Patient Status: Yes',
            'Patient Status: No',
            'Patient Status: No Access',
            'Patient Status: Unknown',
            'Core Participant Minus PM Date',
            'Date of Primary Re-Consent',
            'Date of EHR Re-Consent',
            'Health Data Stream Sharing Status',
            'Health Data Stream Sharing Date',
            'Enrolled Participant Date',
            'Participant Date',
            'Participant + EHR Date',
            'PM&B Eligible Date',
        ], $exportHeaders);
    }

    public function testGetFilterLabelOptionPairs()
    {
        $filterLabelOptionPairs = [
            'labels' => [
                'enrollmentStatusV3_2' => 'Participant Status',
                'activityStatus' => 'Activity Status',
                'patientStatus' => 'Patient Status',
                'pediatricStatus' => 'Pediatric Status',
                'consentForStudyEnrollment' => 'Primary Consent',
                'questionnaireOnDnaProgram' => 'Program Update',
                'consentForElectronicHealthRecords' => 'EHR Consent Status',
                'consentForGenomicsROR' => 'gRoR Consent Status',
                'EtMConsent' => 'Exploring The Mind Consent',
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
                'enrollmentSite' => 'Enrollment Site',
                'selfReportedPhysicalMeasurementsStatus' => 'Remote Phys Measurements',
                'clinicPhysicalMeasurementsStatus' => 'Phys Measurements',
                'sampleStatus1SST8' => '8 mL SST',
                'sampleStatus1PST8' => '8 mL PST',
                'sampleStatus1PS4A' => '1st 4.5 mL PST',
                'sampleStatus1PS4B' => '2nd 4.5 mL PST',
                'sampleStatus1HEP4' => '4 mL Na-Hep',
                'sampleStatus1ED02' => '2 mL EDTA (1ED02)',
                'sampleStatus2ED02' => '2 mL EDTA (2ED02)',
                'sampleStatus1ED04' => '4 mL EDTA (1ED04)',
                'sampleStatus2ED04' => '4 mL EDTA (2ED04)',
                'sampleStatus1ED10' => '1st 10 mL EDTA',
                'sampleStatus2ED10' => '2nd 10 mL EDTA',
                'sampleStatus1CFD9' => 'Cell-Free DNA',
                'sampleStatus1PXR2' => 'Paxgene RNA',
                'sampleStatus1UR10' => 'Urine 10 mL',
                'sampleStatus1UR90' => 'Urine 90 mL',
                'sampleStatus1SAL' => 'Saliva',
                'NphStudyStatus' => 'Nutrition For Precision Health'
            ],
            'options' =>
                [
                    'enrollmentStatusV3_2' =>
                        [
                            '' => 'View All',
                            'PARTICIPANT' => 'Participant',
                            'PARTICIPANT_PLUS_EHR' => 'Participant + EHR Consent',
                            'ENROLLED_PARTICIPANT' => 'Enrolled Participant',
                            'PMB_ELIGIBLE' => 'PM&B Eligible',
                            'CORE_MINUS_PM' => 'Core Participant Minus PM',
                            'CORE_PARTICIPANT' => 'Core Participant',
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
                    'pediatricStatus' =>
                        [
                            '' => 'View All',
                            'SUBMITTED' => 'Pediatric Participant',
                            'UNSET' => 'Adult Participant',
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
                            'SUBMITTED_NOT_VALIDATED' => 'Processing',
                            'SUBMITTED_INVALID' => 'Invalid',
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
                    'EtMConsent' =>
                        [
                            '' => 'View All',
                            'SUBMITTED' => 'Consented Yes',
                            'SUBMITTED_NO_CONSENT' => 'Refused Consent',
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
                    'selfReportedPhysicalMeasurementsStatus' =>
                        [
                            '' => 'View All',
                            'COMPLETED' => 'Completed',
                            'UNSET' => 'Not Completed',
                        ],
                    'clinicPhysicalMeasurementsStatus' =>
                        [
                            '' => 'View All',
                            'COMPLETED' => 'Completed',
                            'UNSET' => 'Not Completed',
                        ],
                    'sampleStatus1SST8' =>
                        [
                            '' => 'View All',
                            'RECEIVED' => 'Received',
                            'UNSET' => 'Not Received',
                        ],
                    'sampleStatus1PST8' =>
                        [
                            '' => 'View All',
                            'RECEIVED' => 'Received',
                            'UNSET' => 'Not Received',
                        ],
                    'sampleStatus1PS4A' =>
                        [
                            '' => 'View All',
                            'RECEIVED' => 'Received',
                            'UNSET' => 'Not Received',
                        ],
                    'sampleStatus1PS4B' =>
                        [
                            '' => 'View All',
                            'RECEIVED' => 'Received',
                            'UNSET' => 'Not Received',
                        ],
                    'sampleStatus1HEP4' =>
                        [
                            '' => 'View All',
                            'RECEIVED' => 'Received',
                            'UNSET' => 'Not Received',
                        ],

                    'sampleStatus1ED02' =>
                        [
                            '' => 'View All',
                            'RECEIVED' => 'Received',
                            'UNSET' => 'Not Received',
                        ],

                    'sampleStatus2ED02' =>
                        [
                            '' => 'View All',
                            'RECEIVED' => 'Received',
                            'UNSET' => 'Not Received',
                        ],

                    'sampleStatus1ED04' =>
                        [
                            '' => 'View All',
                            'RECEIVED' => 'Received',
                            'UNSET' => 'Not Received',
                        ],

                    'sampleStatus2ED04' =>
                        [
                            '' => 'View All',
                            'RECEIVED' => 'Received',
                            'UNSET' => 'Not Received',
                        ],

                    'sampleStatus1ED10' =>
                        [
                            '' => 'View All',
                            'RECEIVED' => 'Received',
                            'UNSET' => 'Not Received',
                        ],

                    'sampleStatus2ED10' =>
                        [
                            '' => 'View All',
                            'RECEIVED' => 'Received',
                            'UNSET' => 'Not Received',
                        ],

                    'sampleStatus1CFD9' =>
                        [
                            '' => 'View All',
                            'RECEIVED' => 'Received',
                            'UNSET' => 'Not Received',
                        ],

                    'sampleStatus1PXR2' =>
                        [
                            '' => 'View All',
                            'RECEIVED' => 'Received',
                            'UNSET' => 'Not Received',
                        ],

                    'sampleStatus1UR10' =>
                        [
                            '' => 'View All',
                            'RECEIVED' => 'Received',
                            'UNSET' => 'Not Received',
                        ],

                    'sampleStatus1UR90' =>
                        [
                            '' => 'View All',
                            'RECEIVED' => 'Received',
                            'UNSET' => 'Not Received',
                        ],

                    'sampleStatus1SAL' =>
                        [
                            '' => 'View All',
                            'RECEIVED' => 'Received',
                            'UNSET' => 'Not Received',
                        ],
                    'NphStudyStatus' =>
                    [
                        '' => 'View All',
                        'NOT_CONSENTED' => 'Not Consented',
                        'MODULE_1_CONSENTED' => 'Module 1 Consented',
                    ]

                ]
        ];
        $filterLabelOptionPairs['labels'] = array_merge(
            $filterLabelOptionPairs['labels'],
            WorkQueue::$filterDateFieldLabels
        );
        $advancedFilters = WorkQueue::$consentAdvanceFilters;
        $this->assertSame($filterLabelOptionPairs, WorkQueue::getFilterLabelOptionPairs($advancedFilters));
    }

    /**
     * @dataProvider getIncentivesDataProvider
     */
    public function testGetParticipantIncentiveDateGiven($incentives, $incentiveDate): void
    {
        $this->assertSame($incentiveDate, WorkQueue::getParticipantIncentiveDateGiven($incentives));
    }

    public function getIncentivesDataProvider(): array
    {
        return [
            [
                [
                    (object) [
                        'incentiveId' => 1,
                        'cancelled' => false,
                        'dateGiven' => '2022-10-19T00:00:00Z'
                    ],
                    (object) [
                        'incentiveId' => 2,
                        'cancelled' => true,
                        'dateGiven' => '2022-10-20T00:00:00Z'
                    ],
                ],
                '10/19/2022'
            ],
            [
                [
                    (object) [
                        'incentiveId' => 1,
                        'cancelled' => false,
                        'dateGiven' => '2022-10-12T00:00:00Z'
                    ],
                    (object) [
                        'incentiveId' => 2,
                        'cancelled' => true,
                        'dateGiven' => '2022-10-19T00:00:00Z'
                    ],
                    (object) [
                        'incentiveId' => 3,
                        'cancelled' => true,
                        'dateGiven' => '2022-10-20T00:00:00Z'
                    ],
                ],
                '10/12/2022'
            ],
            [
                [
                    (object) [
                        'incentiveId' => 1,
                        'cancelled' => true,
                        'dateGiven' => '2022-10-12T00:00:00Z'
                    ]
                ],
                ''
            ]
        ];
    }

    public function testGetHealthDataSharingStatus(): void
    {
        $dateTime = new \DateTime('now');
        $time = $dateTime->format('D M d, Y G:i');
        $dateTime->setTimezone(new \DateTimeZone('America/Chicago'));
        $this->assertSame('<i class="fa fa-times text-danger" aria-hidden="true"></i> (Never Shared)', WorkQueue::getHealthDataSharingStatus(null, null, 'America/Chicago'));
        $this->assertSame('<i class="fa fa-check text-success" aria-hidden="true"></i> ' . $dateTime->format('n/j/Y g:i a') . ' (Ever Shared) ', WorkQueue::getHealthDataSharingStatus('EVER_SHARED', $time, 'America/Chicago'));
        $this->assertSame('<i class="fa fa-check-double text-success" aria-hidden="true"></i> ' . $dateTime->format('n/j/Y g:i a') . ' (Currently Sharing) ', WorkQueue::getHealthDataSharingStatus('CURRENTLY_SHARING', $time, 'America/Chicago'));
        $this->assertSame('<i class="fa fa-times text-danger" aria-hidden="true"></i> (Never Shared)', WorkQueue::getHealthDataSharingStatus('NEVER_SHARED', null, 'America/Chicago'));
    }

    public function testCsvHealthDataSharingStatus(): void
    {
        $dateTime = new \DateTime('now');
        $time = $dateTime->format('D M d, Y G:i');
        $dateTime->setTimezone(new \DateTimeZone('America/Chicago'));
        $this->assertSame(0, WorkQueue::csvHealthDataSharingStatus(null, 'healthDataSharingStatus', false, 'America/Chicago'));
        $this->assertSame(1, WorkQueue::csvHealthDataSharingStatus('EVER_SHARED', 'healthDataSharingStatus', false, 'America/Chicago'));
        $this->assertSame(2, WorkQueue::csvHealthDataSharingStatus('CURRENTLY_SHARING', 'healthDataSharingStatus', false, 'America/Chicago'));
        $this->assertSame($dateTime->format('n/j/Y g:i a'), WorkQueue::csvHealthDataSharingStatus($time, 'healthDataSharingStatus', true, 'America/Chicago'));
        $this->assertSame('', WorkQueue::csvHealthDataSharingStatus(null, 'healthDataSharingStatus', true, 'America/Chicago'));
    }

    public function getNphStudyStatusDataProvider(): array
    {
        return [
            [
                ['nphWithdrawal' => true, 'nphWithdrawalAuthored' => '2022-10-12T00:00:00Z'],
                '<i class="fa fa-times text-danger" aria-hidden="true"></i> 10/11/2022 (Withdrawn)'
            ],
            [
                ['nphDeactivation' => true, 'nphDeactivationAuthored' => '2022-10-12T00:00:00Z'],
                '<i class="fa fa-times text-danger" aria-hidden="true"></i> 10/11/2022 (Deactivated)'
            ],
            [
                ['consentForNphModule1' => true, 'consentForNphModule1Authored' => '2022-10-12T00:00:00Z'],
                '<i class="fa fa-check text-success" aria-hidden="true"></i> 10/11/2022 Module 1 (Consented)'
            ],
            [
                [],
                '<i class="fa fa-times text-danger" aria-hidden="true"></i> (Not Consented)'
            ]
        ];
    }

    /**
     * @dataProvider getNphStudyStatusDataProvider
     */
    public function testGetNphStudyStatus($rdrData, $expected): void
    {
        $participant = $this->testSetup->generateParticipant(null, null, null, null, $rdrData);
        $this->assertSame($expected, WorkQueue::getNphStudyStatus($participant, 'America/Chicago'));
    }

    public function getCsvNphStudyStatusDataProvider(): array
    {
        return [
            [
                ['nphWithdrawal' => true, 'nphWithdrawalAuthored' => '2022-10-12T00:00:00Z'],
                ['nphWithdrawal' => 1, 'nphWithdrawalAuthored' => '10/11/2022 7:00 pm', 'nphDeactivation' => 0, 'nphDeactivationAuthored' => '', 'consentForNphModule1' => 0, 'consentForNphModule1Authored' => '']
            ],
            [
                ['nphDeactivation' => true, 'nphDeactivationAuthored' => '2022-10-12T00:00:00Z'],
                ['nphWithdrawal' => 0, 'nphWithdrawalAuthored' => '', 'nphDeactivation' => 1, 'nphDeactivationAuthored' => '10/11/2022 7:00 pm', 'consentForNphModule1' => 0, 'consentForNphModule1Authored' => '']
            ],
            [
                ['consentForNphModule1' => true, 'consentForNphModule1Authored' => '2022-10-12T00:00:00Z'],
                ['nphWithdrawal' => 0,  'nphWithdrawalAuthored' => '', 'nphDeactivation' => 0, 'nphDeactivationAuthored' => '', 'consentForNphModule1' => 1, 'consentForNphModule1Authored' => '10/11/2022 7:00 pm']
            ],
            [
                [],
                ['nphWithdrawal' => 0, 'nphWithdrawalAuthored' => '', 'nphDeactivation' => 0, 'nphDeactivationAuthored' => '', 'consentForNphModule1' => 0, 'consentForNphModule1Authored' => '']
            ]
        ];
    }
    /**
     * @dataProvider getCsvNphStudyStatusDataProvider
     */
    public function testGetCsvNphStudyStatus($rdrData, $expected): void
    {
        $participant = $this->testSetup->generateParticipant(null, null, null, null, $rdrData);
        $fieldKeys = Workqueue::$columnsDef['NPHConsent']['csvNames'];
        foreach (array_keys($fieldKeys) as $fieldKey) {
            $this->assertSame($expected[$fieldKey], WorkQueue::getCsvNphStudyStatus($participant, $fieldKey,'America/Chicago'));
        }
    }

    public function samplesDataProvider(): array
    {
        return [
            [true, [
                '1ED02' => '2 mL EDTA (1ED02)',
                '2ED02' => '2 mL EDTA (2ED02)',
                '1ED04' => '4 mL EDTA (1ED04)',
                '2ED04' => '4 mL EDTA (2ED04)',
                '1ED10' => '1st 10 mL EDTA',
                '1PXR2' => 'Paxgene RNA',
                '1UR10' => 'Urine 10 mL',
                '1SAL' => 'Saliva'
            ]],
            [false, [
                '1SST8' => '8 mL SST',
                '1PST8' => '8 mL PST',
                'PS04A' => '1st 4.5 mL PST',
                'PS04B' => '2nd 4.5 mL PST',
                '1HEP4' => '4 mL Na-Hep',
                '1ED02' => '2 mL EDTA (1ED02)',
                '1ED04' => '4 mL EDTA (1ED04)',
                '1ED10' => '1st 10 mL EDTA',
                '2ED10' => '2nd 10 mL EDTA',
                '1CFD9' => 'Cell-Free DNA',
                '1PXR2' => 'Paxgene RNA',
                '1UR10' => 'Urine 10 mL',
                '1UR90' => 'Urine 90 mL',
                '1SAL' => 'Saliva'
            ]]
        ];
    }

    /**
     * @dataProvider samplesDataProvider
     */
    public function testGetSamples(bool $isPediatric, array $expectedResult): void
    {
        $this->assertEquals($expectedResult, WorkQueue::getParticipantSummarySamples($isPediatric));
    }

    public function surveysDataProvider(): array
    {
        return [
            [true, [
                'TheBasics' => 'Basics',
                'OverallHealth' => 'Health',
                'EnvironmentalExposures' => 'Environmental Exposures'
            ]],
            [false, [
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
            ]]
        ];
    }

    /**
     * @dataProvider surveysDataProvider
     */
    public function testGetSurveys(bool $isPediatric, array $expectedResult)
    {
        $this->assertEquals($expectedResult, WorkQueue::getParticipantSummarySurveys($isPediatric));
    }
}
