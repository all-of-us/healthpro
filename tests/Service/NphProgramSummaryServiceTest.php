<?php

namespace App\Tests\Service;

use App\Service\LoggerService;
use App\Service\Nph\NphOrderService;
use App\Service\Nph\NphProgramSummaryService;
use App\Service\RdrApiService;
use App\Service\SiteService;
use App\Service\UserService;
use App\Tests\testSetup;
use Doctrine\ORM\EntityManagerInterface;

class NphProgramSummaryServiceTest extends ServiceTestCase
{
    private NphProgramSummaryService $service;
    private $module1data;
    private testSetup $testSetup;
    private UserService $userService;
    private SiteService $siteService;
    private NphOrderService $nphOrderService;

    public function setUp(): void
    {
        parent::setUp();
        $this->program = 'nph';
        $this->login('test-nph-user1@example.com', ['nph-site-test'], 'America/Chicago');
        $siteService = static::getContainer()->get(SiteService::class);
        $siteService->switchSite('nph-site-test' . '@' . self::GROUP_DOMAIN);
        $this->service = new NphProgramSummaryService();
        $this->module1data = json_decode(file_get_contents(__DIR__ . '/data/order_module_1.json'), true);
        $this->userService = static::getContainer()->get(UserService::class);
        $this->siteService = $siteService;
        $this->testSetup = new TestSetup(static::getContainer()->get(EntityManagerInterface::class));
        $this->nphOrderService = new NphOrderService(
            static::getContainer()->get(EntityManagerInterface::class),
            static::getContainer()->get(UserService::class),
            static::getContainer()->get(SiteService::class),
            $this->createMock(LoggerService::class),
            $this->createMock(RdrApiService::class)
        );
    }

    public function testGetModules(): void
    {
        $NphModulesDirectory = __DIR__ . '/../../src/Nph/Order/Modules';
        $this->assertDirectoryExists($NphModulesDirectory);
        $this->assertDirectoryIsReadable($NphModulesDirectory);
        $modules = $this->service->getModules();
        $this->assertIsArray($modules);
        //Get number of files in the directory __DIR__ . '/../../src/Nph/Order/Modules'
        $files = scandir($NphModulesDirectory, SCANDIR_SORT_NONE);
        $files = array_diff($files, ['.', '..']);
        $this->assertCount(count($files), $modules);
        $this->assertContainsOnly('string', $modules);
    }

    public function testGetProgramSummary(): void
    {
        $programSummary = $this->service->getProgramSummary();
        $this->assertIsArray($programSummary);
        $this->assertNotEmpty($programSummary);
        $this->assertContainsOnly('array', $programSummary);
        $this->assertArrayHasKey('1', $programSummary);
        $this->assertArrayHasKey('2', $programSummary);
        $this->assertArrayHasKey('3', $programSummary);
        $this->assertSame(array_keys($this->module1data['timePoints']), array_keys($programSummary['1']['LMT']['visitInfo']));
        foreach ($programSummary['1']['LMT']['visitInfo'] as $timepoint => $timepointInfo) {
            $this->assertSame($timepointInfo['timePointDisplayName'], $this->module1data['timePoints'][$timepoint]);
        }
    }

    public function testCombineProgramAndOrderSummary()
    {
        $programSummary = $this->service->getProgramSummary();
        $nphOrder = $this->testSetup->generateNPHOrder($this->testSetup->generateNphParticipant(), $this->userService->getUserEntity(), $this->siteService);
        $orderSummary = $this->nphOrderService->getParticipantOrderSummary($nphOrder->getParticipantId());
        $combinedSummary = $this->service->combineOrderSummaryWithProgramSummary($orderSummary, $programSummary);
        $this->assertIsArray($combinedSummary);
        $this->assertNotEmpty($combinedSummary);
        $this->assertContainsOnly('array', $combinedSummary);
        $this->assertArrayHasKey('1', $combinedSummary);
        $this->assertArrayHasKey('2', $combinedSummary);
        $this->assertArrayHasKey('3', $combinedSummary);
        $this->assertSame(array_keys($this->module1data['timePoints']), array_keys($combinedSummary['1']['LMT']['visitInfo']));
        foreach ($combinedSummary['1']['LMT']['visitInfo'] as $timepoint => $timepointInfo) {
            $this->assertSame($timepointInfo['timePointDisplayName'], $this->module1data['timePoints'][$timepoint]);
        }
    }

    public function testGetSampleStatusCounts(): void
    {
        $programSummary = $this->service->getProgramSummary();
        $nphOrder = $this->testSetup->generateNPHOrder($this->testSetup->generateNphParticipant(), $this->userService->getUserEntity(), $this->siteService);
        $orderSummary = $this->nphOrderService->getParticipantOrderSummary($nphOrder->getParticipantId());
        $combinedSummary = $this->service->combineOrderSummaryWithProgramSummary($orderSummary, $programSummary);
        $sampleStatusCounts = $this->nphOrderService->getSampleStatusCounts($combinedSummary);
        $this->assertIsArray($sampleStatusCounts);
        $this->assertNotEmpty($sampleStatusCounts);
        $this->assertContainsOnly('array', $sampleStatusCounts);
        $this->assertArrayHasKey('1', $sampleStatusCounts);
        $this->assertArrayHasKey('2', $sampleStatusCounts);
        $this->assertArrayHasKey('3', $sampleStatusCounts);
        $this->assertArrayHasKey('active', $sampleStatusCounts['1']);
        $this->assertArrayHasKey('active', $sampleStatusCounts['2']);
        $this->assertArrayHasKey('active', $sampleStatusCounts['3']);
        $this->assertArrayHasKey('Created', $sampleStatusCounts['1']);
        $this->assertEquals(1, $sampleStatusCounts['1']['active']);
        $this->assertEquals(1, $sampleStatusCounts['1']['Created']);
    }
}
