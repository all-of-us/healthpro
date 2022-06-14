<?php

namespace App\Tests\Service;

use App\Entity\Incentive;
use App\Entity\IncentiveImport;
use App\Entity\User;
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

    /**
     * @dataProvider incentiveImportDataProvider
     */
    public function testGetIncentiveFromImportData($importData)
    {
        $incentive = $this->service->getIncentiveFromImportData($importData, new IncentiveImport());
        $this->assertEquals($incentive->getIncentiveDateGiven(), $importData['incentiveDateGiven']);
        $this->assertEquals($incentive->getIncentiveType(), $importData['incentiveType']);
        $this->assertEquals($incentive->getOtherIncentiveType(), $importData['otherIncentiveType']);
        $this->assertEquals($incentive->getIncentiveOccurrence(), $importData['incentiveOccurrence']);
        $this->assertEquals($incentive->getOtherIncentiveOccurrence(), $importData['otherIncentiveOccurrence']);
        $this->assertEquals($incentive->getIncentiveAmount(), $importData['incentiveAmount']);
        $this->assertEquals($incentive->getGiftCardType(), $importData['giftCardType']);
        $this->assertEquals($incentive->getNotes(), $importData['notes']);
        $this->assertEquals($incentive->getDeclined(), $importData['declined']);
        $this->assertEquals($incentive->getParticipantId(), $importData['participantId']);
        $this->assertEquals($incentive->getSite(), $importData['site']);
    }

    public function incentiveImportDataProvider()
    {
        return [
            [
                [
                    'incentiveDateGiven' => new \Datetime('06/03/2022'),
                    'incentiveType' => 'cash',
                    'otherIncentiveType' => '',
                    'incentiveOccurrence' => 'one_time',
                    'otherIncentiveOccurrence' => '',
                    'incentiveAmount' => 15,
                    'giftCardType' => '',
                    'notes' => '',
                    'declined' => 0,
                    'participantId' => 'P000000001',
                    'site' => 'test-site1'
                ],
                [
                    'incentiveDateGiven' => new \Datetime('06/03/2022'),
                    'incentiveType' => 'promotional',
                    'otherIncentiveType' => '',
                    'incentiveOccurrence' => 'redraw',
                    'otherIncentiveOccurrence' => '',
                    'incentiveAmount' => 0,
                    'giftCardType' => '',
                    'notes' => 'Test notes',
                    'declined' => 1,
                    'participantId' => 'P000000002',
                    'site' => 'test-site2'
                ]
            ]
        ];
    }

    public function testRdrObject(): void
    {
        $incentive = $this->createIncentive();
        $rdrObject = $this->service->getRdrObject($incentive, $this->getUser());
        self::assertEquals('test@example.com', $rdrObject->createdBy);
        self::assertEquals('hpo-site-test', $rdrObject->site);
        self::assertEquals('redraw', $rdrObject->occurrence);
        self::assertEquals(new \DateTime('2022-06-03'), $rdrObject->dateGiven);
        self::assertEquals('redraw', $rdrObject->occurrence);
        self::assertEquals('gift_card', $rdrObject->incentiveType);
        self::assertEquals('target', $rdrObject->giftcardType);
        self::assertEquals(15, $rdrObject->amount);
    }

    private function createIncentive(): Incentive
    {
        $incentive = new Incentive();
        $incentive
            ->setCreatedTs(new \DateTime())
            ->setIncentiveDateGiven(new \DateTime('2022-06-03'))
            ->setOtherIncentiveOccurrence('redraw')
            ->setOtherIncentiveType('gift_card')
            ->setGiftCardType('target')
            ->setIncentiveAmount(15)
            ->setSite('test');
        return $incentive;
    }

    protected function getUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        return $user;
    }
}
