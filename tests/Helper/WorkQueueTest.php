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
}
