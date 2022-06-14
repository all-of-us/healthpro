<?php

namespace App\Tests\Service;

use App\Form\IdVerificationImportFormType;
use App\Service\IdVerificationImportService;
use App\Service\SiteService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class IdVerificationImportServiceTest extends ServiceTestCase
{
    protected $service;
    protected $id;

    public function setUp(): void
    {
        parent::setUp();
        $this->login('test@example.com', ['hpo-site-test'], 'America/Chicago');
        $siteService = static::$container->get(SiteService::class);
        $siteService->switchSite('hpo-site-test' . '@' . self::GROUP_DOMAIN);
        $this->service = static::$container->get(IdVerificationImportService::class);
    }

    /**
     * @dataProvider csvFileDataProvider
     */
    public function testExtractCsvFileData($fileName, $isValid, $rows = null)
    {
        $form = static::$container->get('form.factory')->create(IdVerificationImportFormType::class, null, ['csrf_protection' => false]);
        $form->submit([
            'id_verification_csv' => $this->createUploadedFile($fileName)
        ]);
        $file = $form['id_verification_csv']->getData();
        $incentives = $this->service->extractCsvFileData($file, $form);
        $this->assertEquals($form->isValid(), $isValid);
        if ($form->isValid()) {
            $this->assertEquals($rows, count($incentives));
        }
    }

    public function csvFileDataProvider()
    {
        return [
            ['id_verification_import.csv', true, 3],
            ['id_verification_import_invalid.csv', false]
        ];
    }

    private function createUploadedFile($fileName)
    {
        $fileName = __DIR__ . '/data/' . $fileName;
        return new UploadedFile($fileName, 'id_verification_import.csv', 'text/csv', null, true);
    }
}
