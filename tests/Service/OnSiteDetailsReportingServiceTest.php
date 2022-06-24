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
        $ajaxData = $this->service->getAjaxData($this->getPatientStatusData());
        $this->assertEquals('06-24-2022', $ajaxData[0]['created']);
        $this->assertEquals('test1@example.com', $ajaxData[0]['user']);
        $this->assertEquals('YES', $ajaxData[0]['patientStatus']);
        $this->assertEquals('06-24-2022', $ajaxData[1]['created']);
        $this->assertEquals('test2@example.com', $ajaxData[1]['user']);
        $this->assertEquals('NO', $ajaxData[1]['patientStatus']);
    }

    private function getPatientStatusData(): array
    {
        $now = new \Datetime('2022-06-24');
        return [
            [
                'createdTs' => $now,
                'participantId' => 'P000000000',
                'email' => 'test1@example.com',
                'site' => 'PS_SITE_TEST',
                'status' => 'YES',
                'comments' => 'test1',
                'importId' => 1,
            ],
            [
                'createdTs' => $now,
                'participantId' => 'P000000002',
                'email' => 'test2@example.com',
                'site' => 'PS_SITE_TEST',
                'status' => 'NO',
                'comments' => 'test2',
                'importId' => 2,
            ]
        ];
    }
}
