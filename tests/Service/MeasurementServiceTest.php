<?php

namespace App\Tests\Service;

use App\Audit\Log;
use App\Entity\Measurement;
use App\Entity\Site;
use App\Form\SiteType;
use App\Helper\PpscParticipant;
use App\Repository\MeasurementRepository;
use App\Service\LoggerService;
use App\Service\MeasurementService;
use App\Service\Ppsc\PpscApiService;
use App\Service\SiteService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class MeasurementServiceTest extends ServiceTestCase
{
    protected $siteService;
    protected $measurementService;
    protected $id;

    public function setUp(): void
    {
        parent::setUp();
        $this->siteService = static::getContainer()->get(SiteService::class);
        $this->measurementService = static::getContainer()->get(MeasurementService::class);
    }

    public function testRequireBloodDonorCheck(): void
    {
        $this->id = uniqid();
        $site = 'hpo-site-test' . $this->id;
        $hybridSite = 'hpo-site-test' . SiteType::DV_HYBRID . $this->id;
        $this->login('test@example.com', [$site, $hybridSite]);
        // Regular site
        $this->createSite();
        $this->siteService->switchSite($site . '@' . self::GROUP_DOMAIN);
        self::assertFalse($this->measurementService->requireBloodDonorCheck());
        // Hybrid site
        $this->createSite(SiteType::DV_HYBRID);
        $this->siteService->switchSite($hybridSite . '@' . self::GROUP_DOMAIN);
        self::assertTrue($this->measurementService->requireBloodDonorCheck());
    }

    /**
     * @dataProvider siteStatusProvider
     */
    public function testInactiveSiteFormDisabled($parentId, $isActiveSite, $expectedResult): void
    {
        $mockSiteService = $this->createMock(SiteService::class);
        $mockSiteService->method('isActiveSite')->willReturn($isActiveSite);

        $measurementService = new MeasurementService(
            static::getContainer()->get(EntityManagerInterface::class),
            static::getContainer()->get(RequestStack::class),
            static::getContainer()->get(UserService::class),
            static::getContainer()->get(PpscApiService::class),
            $mockSiteService,
            static::getContainer()->get(ParameterBagInterface::class),
            static::getContainer()->get(LoggerService::class),
        );

        $measurementMock = $this->getMockBuilder(Measurement::class)
            ->getMock();

        $measurementMock->expects($this->any())
            ->method('getParentId')
            ->willReturn($parentId);

        $reflection = new \ReflectionClass($measurementService);
        $property = $reflection->getProperty('measurement');
        $property->setValue($measurementService, $measurementMock);

        $result = $measurementService->inactiveSiteFormDisabled();
        $this->assertSame($expectedResult, $result);
    }

    public function siteStatusProvider(): array
    {
        return [
            'No parent ID, inactive site: expect true' => [null, false, true],
            'Parent ID, inactive site: expect false' => [123, false, false],
            'No parent ID, active site: expect false' => [null, true, false],
            'Parent ID, active site: expect false' => [123, true, false]
        ];
    }

    /**
     * @dataProvider backfillMeasurementsProvider
     */
    public function testBackfillMeasurementsSexAtBirth($participantData, $expectsSetSexAtBirth, $expectsPersist, $expectsApiErrorLog)
    {
        $measurement = $this->createMock(Measurement::class);
        $measurement->method('getParticipantId')->willReturn('123');

        $repository = $this->createMock(MeasurementRepository::class);
        $repository->method('getMissingSexAtBirthPediatricMeasurements')->willReturn([$measurement]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('getParticipantById')->willReturn($participantData);
        $loggerService = $this->createMock(LoggerService::class);

        if ($expectsSetSexAtBirth) {
            $measurement->expects($this->once())->method('setSexAtBirth')->with($participantData->sexAtBirth);
        } else {
            $measurement->expects($this->never())->method('setSexAtBirth');
        }

        if ($expectsPersist) {
            $entityManager->expects($this->once())->method('persist')->with($measurement);
        } else {
            $entityManager->expects($this->never())->method('persist');
        }
        if ($expectsApiErrorLog) {
            $loggerService->expects($this->once())
                ->method('log')
                ->with(Log::PPSC_API_ERROR, $this->stringContains('participant ID: 123'));
        } else {
            $loggerService->expects($this->never())
                ->method('log');
        }

        $entityManager->expects($this->once())->method('flush');

        $measurementService = new MeasurementService(
            $entityManager,
            static::getContainer()->get(RequestStack::class),
            static::getContainer()->get(UserService::class),
            $ppscApiService,
            static::getContainer()->get(SiteService::class),
            static::getContainer()->get(ParameterBagInterface::class),
            $loggerService
        );

        $measurementService->backfillMeasurementsSexAtBirth();
    }

    public function backfillMeasurementsProvider(): array
    {
        return [
            'Valid sexAtBirth data' => [
                new PpscParticipant((object) ['sexAtBirth' => 1]),
                true,  // Expects setSexAtBirth
                true,  // Expects persist
                false  // Expects API error log
            ],
            'Null participant data' => [
                null,
                false,
                false,
                true
            ],
        ];
    }

    private function createSite($hybrid = null): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $orgId = 'TEST_ORG_' . $hybrid . $this->id;
        $siteId = 'test' . $hybrid . $this->id;
        $site = new Site();
        $site->setStatus(true)
            ->setName('Test Site ' . $hybrid . $this->id)
            ->setOrganizationId($orgId)
            ->setSiteId($siteId)
            ->setGoogleGroup($siteId)
            ->setWorkqueueDownload('')
            ->setType('DV')
            ->setDvModule($hybrid);
        $em->persist($site);
        $em->flush();
    }
}
