<?php
namespace App\Tests\Service;

use App\Service\HelpService;

class HelpServiceTest extends ServiceTestCase
{
    public function testGetDocumentTitlesList(): void
    {
        $helpService = static::getContainer()->get(HelpService::class);
        $documentList = $helpService->getDocumentTitlesList();
        self::assertIsArray($documentList);
        self::assertGreaterThan(0, count($documentList));
        self::assertArrayHasKey('SOP-014', $documentList);
        self::assertIsString($documentList['SOP-014']);
    }
}
