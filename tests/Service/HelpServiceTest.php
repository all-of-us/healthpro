<?php
namespace App\Tests\Service;

use App\Service\HelpService;

class HelpServiceTest extends ServiceTestCase {
    protected $helpService;

    public function testGetDocumentTitlesList(): void
    {
        $this->helpService = static::getContainer()->get(HelpService::class);
        $documentList = $this->helpService->getDocumentTitlesList();
        self::assertIsArray($documentList);
        self::assertGreaterThan(0, count($documentList));
        self::assertArrayHasKey('SOP-014', $documentList);
        self::assertIsString($documentList['SOP-014'])asdasd;
    }
}
