<?php

namespace App\Tests\Service;

use App\Form\IncentiveImportFormType;
use App\Service\IncentiveImportService;
use App\Service\SiteService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class IncentiveImportServiceTest extends ServiceTestCase
{
    protected $service;
    protected $id;

    public function setUp(): void
    {
        parent::setUp();
        $this->login('test@example.com', ['hpo-site-test'], 'America/Chicago');
        $siteService = static::$container->get(SiteService::class);
        $siteService->switchSite('hpo-site-test' . '@' . self::GROUP_DOMAIN);
        $this->service = static::$container->get(IncentiveImportService::class);
    }

    /**
     * @dataProvider emailDataProvider
     */
    public function testValidEmail($email, $isValid): void
    {
        $result = $this->service->isValidEmail($email);
        $this->assertEquals($result, $isValid);
    }

    public function emailDataProvider()
    {
        return [
            ['test-1@pmi-ops.org', true],
            ['test-2@pmiops.org', false],
            ['test-3@ops-pmi.org', false],
            ['test-4@pmi-ops.org@ops-pmi.org', false],
            ['pmi-ops.org@ops-pmi.org', false]
        ];
    }

    /**
     * @dataProvider csvFileDataProvider
     */
    public function testExtractCsvFileData($fileName, $isValid, $rows = null)
    {
        $form = static::$container->get('form.factory')->create(IncentiveImportFormType::class, null, ['csrf_protection' => false]);
        $form->submit([
            'incentive_csv' => $this->createUploadedFile($fileName)
        ]);
        $file = $form['incentive_csv']->getData();
        $incentives = $this->service->extractCsvFileData($file, $form);
        $this->assertEquals($form->isValid(), $isValid);
        if ($form->isValid()) {
            $this->assertEquals($rows, count($incentives));
        }
    }

    public function csvFileDataProvider()
    {
        return [
            ['incentive_import.csv', true, 3],
            ['incentive_import_invalid.csv', false]
        ];
    }

    private function createUploadedFile($fileName)
    {
        $fileName = __DIR__ . '/data/' . $fileName;
        return new UploadedFile($fileName, 'incentive_import.csv', 'text/csv', null, true);
    }
}
