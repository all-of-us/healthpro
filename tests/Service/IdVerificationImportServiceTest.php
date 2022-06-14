<?php

namespace App\Tests\Service;

use App\Entity\IdVerificationImportRow;
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

    public function testGetAjaxData()
    {
        $idVerificationImportRows = [];
        $importRows = $this->getIdVerificationImportRows();
        foreach ($importRows as $importRow) {
            $idVerificationImportRow = new IdVerificationImportRow();
            $idVerificationImportRow->setParticipantId($importRow['participantId']);
            $idVerificationImportRow->setUserEmail($importRow['userEmail']);
            $idVerificationImportRow->setVerifiedDate($importRow['verifiedDate']);
            $idVerificationImportRow->setVerificationType($importRow['verificationType']);
            $idVerificationImportRow->setVisitType($importRow['visitType']);
            $idVerificationImportRows[] = $idVerificationImportRow;
        }

        $createdTs = new \Datetime('06/13/2022');
        $ajaxData = $this->service->getAjaxData($idVerificationImportRows, $createdTs);

        $this->assertEquals($ajaxData[0]['participantId'], $importRows[0]['participantId']);
        $this->assertEquals($ajaxData[0]['userEmail'], $importRows[0]['userEmail']);
        $this->assertEquals($ajaxData[0]['verifiedDate'], $importRows[0]['verifiedDate']->format('n/j/Y'));
        $this->assertEquals($ajaxData[0]['verificationType'], $importRows[0]['verificationType']);
        $this->assertEquals($ajaxData[0]['visitType'], $importRows[0]['visitType']);
        $this->assertEquals($ajaxData[0]['status'], $importRows[0]['status']);

        $this->assertEquals($ajaxData[1]['participantId'], $importRows[1]['participantId']);
        $this->assertEquals($ajaxData[1]['userEmail'], $importRows[1]['userEmail']);
        $this->assertEquals($ajaxData[1]['verifiedDate'], $importRows[1]['verifiedDate']->format('n/j/Y'));
        $this->assertEquals($ajaxData[1]['verificationType'], $importRows[1]['verificationType']);
        $this->assertEquals($ajaxData[1]['visitType'], $importRows[1]['visitType']);
        $this->assertEquals($ajaxData[1]['status'], $importRows[1]['status']);
    }

    private function getIdVerificationImportRows(): array
    {
        return [
            [
                'participantId' => 'P000000001',
                'userEmail' => 'test1@example.com',
                'verifiedDate' => new \Datetime('06/13/2022'),
                'verificationType' => 'PHOTO_AND_ONE_OF_PII',
                'visitType' => 'PMB_INITIAL_VISIT',
                'status' => 0
            ],
            [
                'participantId' => 'P000000002',
                'userEmail' => 'test2@example.com',
                'verifiedDate' => new \Datetime('06/14/2022'),
                'verificationType' => 'TWO_OF_PII',
                'visitType' => 'BIOSPECIMEN_REDRAW_ONLY',
                'status' => 0
            ],
        ];
    }
}
