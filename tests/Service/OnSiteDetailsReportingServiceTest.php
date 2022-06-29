<?php

namespace App\Tests\Service;

use App\Service\OnSiteDetailsReportingService;
use App\Service\SiteService;

class OnSiteDetailsReportingServiceTest extends ServiceTestCase
{
    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->login('test@example.com', ['hpo-site-test'], 'America/Chicago');
        $siteService = static::getContainer()->get(SiteService::class);
        $siteService->switchSite('hpo-site-test' . '@' . self::GROUP_DOMAIN);
        $this->service = static::$container->get(OnSiteDetailsReportingService::class);
    }


    public function testAjaxData(): void
    {
        $ajaxData = $this->service->getPatientStatusAjaxData($this->getPatientStatusData());
        $this->assertEquals('06-24-2022', $ajaxData[0]['created']);
        $this->assertEquals('test1@example.com', $ajaxData[0]['user']);
        $this->assertEquals('Yes', $ajaxData[0]['patientStatus']);
        $this->assertEquals('06-24-2022', $ajaxData[1]['created']);
        $this->assertEquals('test2@example.com', $ajaxData[1]['user']);
        $this->assertEquals('No', $ajaxData[1]['patientStatus']);
    }

    private function getPatientStatusData(): array
    {
        $now = new \Datetime('2022-06-24');
        return [
            [
                'createdTs' => $now,
                'participantId' => 'P000000000',
                'email' => 'test1@example.com',
                'siteName' => 'PS_SITE_TEST',
                'status' => 'YES',
                'comments' => 'test1',
                'importId' => 1,
            ],
            [
                'createdTs' => $now,
                'participantId' => 'P000000002',
                'email' => 'test2@example.com',
                'siteName' => 'PS_SITE_TEST',
                'status' => 'NO',
                'comments' => 'test2',
                'importId' => 2,
            ]
        ];
    }

    public function testIncentiveTrackingAjaxData(): void
    {
        $ajaxData = $this->service->getIncentiveTrackingAjaxData($this->getIncentivesData());
        $this->assertEquals('06-29-2022', $ajaxData[0]['created']);
        $this->assertEquals('test1@example.com', $ajaxData[0]['user']);
        $this->assertEquals('Cash', $ajaxData[0]['incentiveType']);
        $this->assertEquals('One-time Incentive', $ajaxData[0]['occurrence']);
        $this->assertEquals('06-29-2022', $ajaxData[1]['created']);
        $this->assertEquals('test2@example.com', $ajaxData[1]['user']);
        $this->assertEquals('Voucher', $ajaxData[1]['incentiveType']);
        $this->assertEquals('Redraw', $ajaxData[1]['occurrence']);
    }

    private function getIncentivesData(): array
    {
        $now = new \Datetime('2022-06-29');
        return [
            [
                'participantId' => 'P000000000',
                'siteName' => 'PS_SITE_TEST',
                'email' => 'test1@example.com',
                'incentiveDateGiven' => $now,
                'incentiveType' => 'cash',
                'otherIncentiveType' => '',
                'incentiveOccurrence' => 'one_time',
                'otherIncentiveOccurrence' => '',
                'incentiveAmount' => '25',
                'giftCardType' => '',
                'notes' => 'test',
                'createdTs' => $now,
                'declined' => 0,
                'importId' => 1,
                'amendedUser' => ''
            ],
            [
                'participantId' => 'P000000001',
                'siteName' => 'PS_SITE_TEST',
                'email' => 'test2@example.com',
                'incentiveDateGiven' => $now,
                'incentiveType' => 'voucher',
                'otherIncentiveType' => '',
                'incentiveOccurrence' => 'redraw',
                'otherIncentiveOccurrence' => '',
                'incentiveAmount' => '25',
                'giftCardType' => '',
                'notes' => 'test',
                'createdTs' => $now,
                'declined' => 0,
                'importId' => 2,
                'amendedUser' => ''
            ]
        ];
    }
}
