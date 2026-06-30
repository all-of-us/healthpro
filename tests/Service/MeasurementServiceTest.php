<?php

namespace App\Tests\Service;

use App\Audit\Log;
use App\Entity\Measurement;
use App\Entity\Site;
use App\Entity\User;
use App\Form\SiteType;
use App\Helper\PpscParticipant;
use App\Repository\MeasurementRepository;
use App\Security\User as SecurityUser;
use App\Service\LoggerService;
use App\Service\MeasurementService;
use App\Service\Ppsc\PpscApiService;
use App\Service\SiteService;
use App\Service\UserService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use GuzzleHttp\Psr7\Response;
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

    public function testGetCurrentVersionPediatric(): void
    {
        $measurementService = $this->buildMeasurementService();
        self::assertSame(
            Measurement::CURRENT_VERSION . '-peds-6-and-under',
            $measurementService->getCurrentVersion('peds-6-and-under')
        );
    }

    public function testGetCurrentVersionDefault(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $measurementService = $this->buildMeasurementService(['em' => $em]);
        self::assertSame(Measurement::CURRENT_VERSION, $measurementService->getCurrentVersion('standard'));
    }

    public function testGetCurrentVersionEhr(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(new Site());
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $measurementService = $this->buildMeasurementService(['em' => $em]);
        self::assertSame(Measurement::EHR_CURRENT_VERSION, $measurementService->getCurrentVersion('standard'));
    }

    /**
     * @dataProvider ehrModificationProtocolProvider
     */
    public function testRequireEhrModificationProtocol($foundSite, bool $expected): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($foundSite);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        $measurementService = $this->buildMeasurementService(['em' => $em]);
        self::assertSame($expected, $measurementService->requireEhrModificationProtocol());
    }

    public function ehrModificationProtocolProvider(): array
    {
        return [
            'Matching site found' => [new Site(), true],
            'No site found' => [null, false],
        ];
    }

    /**
     * @dataProvider canEditProvider
     */
    public function testCanEdit(bool $status, bool $editExistingOnly, $measurementId, bool $expected): void
    {
        $participant = (object) ['status' => $status, 'editExistingOnly' => $editExistingOnly];
        $measurementService = $this->buildMeasurementService();
        self::assertSame($expected, $measurementService->canEdit($measurementId, $participant));
    }

    public function canEditProvider(): array
    {
        return [
            'Active participant' => [true, false, 123, true],
            'Inactive participant, no measurement' => [false, true, null, false],
            'Inactive participant with existing measurement, edit allowed' => [false, true, 123, true],
            'Inactive participant with existing measurement, edit not allowed' => [false, false, 123, false],
        ];
    }

    /**
     * @dataProvider cancelRestoreRdrObjectProvider
     */
    public function testGetCancelRestoreRdrObject(string $type, string $expectedStatus, string $expectedInfoKey): void
    {
        $securityUser = $this->createMock(SecurityUser::class);
        $securityUser->method('getUsername')->willReturn('user@example.com');
        $userService = $this->createMock(UserService::class);
        $userService->method('getUser')->willReturn($securityUser);
        $siteService = $this->createMock(SiteService::class);
        $siteService->method('getSiteId')->willReturn('site-123');

        $measurementService = $this->buildMeasurementService([
            'userService' => $userService,
            'siteService' => $siteService,
        ]);

        $object = $measurementService->getCancelRestoreRdrObject($type, 'A reason');
        self::assertSame($expectedStatus, $object->status);
        self::assertSame('A reason', $object->reason);
        self::assertSame('user@example.com', $object->{$expectedInfoKey}['author']['value']);
        self::assertSame('site-123', $object->{$expectedInfoKey}['site']['value']);
    }

    public function cancelRestoreRdrObjectProvider(): array
    {
        return [
            'Cancel' => [Measurement::EVALUATION_CANCEL, 'cancelled', 'cancelledInfo'],
            'Restore' => [Measurement::EVALUATION_RESTORE, 'restored', 'restoredInfo'],
        ];
    }

    public function testGetLastError(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('getLastError')->willReturn('Something went wrong');
        $measurementService = $this->buildMeasurementService(['ppscApiService' => $ppscApiService]);
        self::assertSame('Something went wrong', $measurementService->getLastError());
    }

    public function testCopyMeasurements(): void
    {
        $currentMeasurement = $this->createMock(Measurement::class);
        $currentMeasurement->method('getId')->willReturn(42);

        $measurementService = $this->buildMeasurementService();
        $this->setMeasurementProperty($measurementService, $currentMeasurement);

        $newMeasurement = $this->createMock(Measurement::class);
        $newMeasurement->expects($this->once())->method('setParentId')->with(42);
        $newMeasurement->expects($this->once())->method('setFinalizedUser')->with(null);
        $newMeasurement->expects($this->once())->method('setFinalizedSite')->with(null);
        $newMeasurement->expects($this->once())->method('setFinalizedTs')->with(null);
        $newMeasurement->expects($this->once())->method('setRdrId')->with(null);

        $measurementService->copyMeasurements($newMeasurement);
    }

    public function testCreateMeasurementSuccess(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('post')->willReturn(new Response(200, [], '{"drcId":"DRC123"}'));
        $measurementService = $this->buildMeasurementService(['ppscApiService' => $ppscApiService]);
        self::assertSame('DRC123', $measurementService->createMeasurement('P1', new \stdClass()));
    }

    public function testCreateMeasurementMissingDrcId(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('post')->willReturn(new Response(200, [], '{}'));
        $measurementService = $this->buildMeasurementService(['ppscApiService' => $ppscApiService]);
        self::assertFalse($measurementService->createMeasurement('P1', new \stdClass()));
    }

    public function testCreateMeasurementException(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('post')->willThrowException(new \Exception('API error'));
        $ppscApiService->expects($this->once())->method('logException');
        $measurementService = $this->buildMeasurementService(['ppscApiService' => $ppscApiService]);
        self::assertFalse($measurementService->createMeasurement('P1', new \stdClass()));
    }

    public function testGetMeasurmeentSuccess(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('get')->willReturn(new Response(200, [], '{"id":"M1"}'));
        $measurementService = $this->buildMeasurementService(['ppscApiService' => $ppscApiService]);
        $result = $measurementService->getMeasurmeent('P1', 'M1');
        self::assertIsObject($result);
        self::assertSame('M1', $result->id);
    }

    public function testGetMeasurmeentNullResponse(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('get')->willReturn(null);
        $measurementService = $this->buildMeasurementService(['ppscApiService' => $ppscApiService]);
        self::assertFalse($measurementService->getMeasurmeent('P1', 'M1'));
    }

    public function testGetMeasurmeentException(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('get')->willThrowException(new \Exception('API error'));
        $ppscApiService->expects($this->once())->method('logException');
        $measurementService = $this->buildMeasurementService(['ppscApiService' => $ppscApiService]);
        self::assertFalse($measurementService->getMeasurmeent('P1', 'M1'));
    }

    /**
     * @dataProvider cancelRestoreMeasurementProvider
     */
    public function testCancelRestoreMeasurement(int $statusCode, bool $expected): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('patch')->willReturn(new Response($statusCode, [], '{}'));
        $measurementService = $this->buildMeasurementService(['ppscApiService' => $ppscApiService]);
        self::assertSame($expected, $measurementService->cancelRestoreMeasurement('P1', 'M1', new \stdClass()));
    }

    public function cancelRestoreMeasurementProvider(): array
    {
        return [
            'Success' => [200, true],
            'Non-200 response' => [400, false],
        ];
    }

    public function testCancelRestoreMeasurementException(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('patch')->willThrowException(new \Exception('API error'));
        $ppscApiService->expects($this->once())->method('logException');
        $measurementService = $this->buildMeasurementService(['ppscApiService' => $ppscApiService]);
        self::assertFalse($measurementService->cancelRestoreMeasurement('P1', 'M1', new \stdClass()));
    }

    public function testCancelRestoreRdrMeasurement(): void
    {
        $securityUser = $this->createMock(SecurityUser::class);
        $securityUser->method('getUsername')->willReturn('user@example.com');
        $userService = $this->createMock(UserService::class);
        $userService->method('getUser')->willReturn($securityUser);
        $siteService = $this->createMock(SiteService::class);
        $siteService->method('getSiteId')->willReturn('site-123');
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('patch')->willReturn(new Response(200, [], '{}'));

        $measurement = $this->createMock(Measurement::class);
        $measurement->method('getParticipantId')->willReturn('P1');
        $measurement->method('getRdrId')->willReturn('R1');

        $measurementService = $this->buildMeasurementService([
            'userService' => $userService,
            'siteService' => $siteService,
            'ppscApiService' => $ppscApiService,
        ]);
        $this->setMeasurementProperty($measurementService, $measurement);

        self::assertTrue($measurementService->cancelRestoreRdrMeasurement(Measurement::EVALUATION_CANCEL, 'A reason'));
    }

    public function testRevertMeasurementSuccess(): void
    {
        $measurement = $this->createMock(Measurement::class);
        $measurement->method('getId')->willReturn(7);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($measurement);
        $em->expects($this->once())->method('flush');
        $loggerService = $this->createMock(LoggerService::class);
        $loggerService->expects($this->once())->method('log')->with(Log::EVALUATION_DELETE, 7);

        $measurementService = $this->buildMeasurementService(['em' => $em, 'loggerService' => $loggerService]);
        self::assertTrue($measurementService->revertMeasurement($measurement));
    }

    public function testRevertMeasurementException(): void
    {
        $measurement = $this->createMock(Measurement::class);
        $measurement->method('getId')->willReturn(7);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush')->willThrowException(new \Exception('DB error'));

        $measurementService = $this->buildMeasurementService(['em' => $em]);
        self::assertFalse($measurementService->revertMeasurement($measurement));
    }

    public function testCreateMeasurementHistorySuccess(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('commit');
        $connection->expects($this->never())->method('rollback');

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->method('find')->willReturn($this->createMock(User::class));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->method('getRepository')->willReturn($userRepository);
        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->exactly(2))->method('flush');

        $securityUser = $this->createMock(SecurityUser::class);
        $securityUser->method('getId')->willReturn(1);
        $userService = $this->createMock(UserService::class);
        $userService->method('getUser')->willReturn($securityUser);
        $siteService = $this->createMock(SiteService::class);
        $siteService->method('getSiteId')->willReturn('site-123');

        $measurement = $this->createMock(Measurement::class);
        $measurement->method('getId')->willReturn(10);

        $measurementService = $this->buildMeasurementService([
            'em' => $em,
            'userService' => $userService,
            'siteService' => $siteService,
            'loggerService' => $this->createMock(LoggerService::class),
        ]);
        $this->setMeasurementProperty($measurementService, $measurement);

        self::assertTrue($measurementService->createMeasurementHistory('finalize', 10, 'A reason'));
    }

    public function testCreateMeasurementHistoryRollbackOnException(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('rollback');
        $connection->expects($this->never())->method('commit');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->method('getRepository')->willThrowException(new \Exception('DB error'));

        $securityUser = $this->createMock(SecurityUser::class);
        $securityUser->method('getId')->willReturn(1);
        $userService = $this->createMock(UserService::class);
        $userService->method('getUser')->willReturn($securityUser);

        $measurement = $this->createMock(Measurement::class);

        $measurementService = $this->buildMeasurementService([
            'em' => $em,
            'userService' => $userService,
        ]);
        $this->setMeasurementProperty($measurementService, $measurement);

        self::assertFalse($measurementService->createMeasurementHistory('finalize', 10, 'A reason'));
    }

    public function testSendToRdrSuccess(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('post')->willReturn(new Response(200, [], '{"drcId":"DRC123"}'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $measurement = $this->createMock(Measurement::class);
        $measurement->method('getParentId')->willReturn(null);
        $measurement->method('getFinalizedTs')->willReturn(new \DateTime());
        $measurement->method('getFhir')->willReturn(new \stdClass());
        $measurement->method('getParticipantId')->willReturn('P1');
        $measurement->expects($this->once())->method('setRdrId')->with('DRC123');

        $measurementService = $this->buildMeasurementService([
            'em' => $em,
            'ppscApiService' => $ppscApiService,
        ]);
        $this->setMeasurementProperty($measurementService, $measurement);

        self::assertTrue($measurementService->sendToRdr());
    }

    public function testSendToRdrFailure(): void
    {
        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('post')->willReturn(new Response(200, [], '{}'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $measurement = $this->createMock(Measurement::class);
        $measurement->method('getParentId')->willReturn(null);
        $measurement->method('getFinalizedTs')->willReturn(new \DateTime());
        $measurement->method('getFhir')->willReturn(new \stdClass());
        $measurement->method('getParticipantId')->willReturn('P1');
        $measurement->expects($this->never())->method('setRdrId');

        $measurementService = $this->buildMeasurementService([
            'em' => $em,
            'ppscApiService' => $ppscApiService,
        ]);
        $this->setMeasurementProperty($measurementService, $measurement);

        self::assertFalse($measurementService->sendToRdr());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function buildMeasurementService(array $overrides = []): MeasurementService
    {
        return new MeasurementService(
            $overrides['em'] ?? static::getContainer()->get(EntityManagerInterface::class),
            $overrides['requestStack'] ?? static::getContainer()->get(RequestStack::class),
            $overrides['userService'] ?? static::getContainer()->get(UserService::class),
            $overrides['ppscApiService'] ?? static::getContainer()->get(PpscApiService::class),
            $overrides['siteService'] ?? static::getContainer()->get(SiteService::class),
            $overrides['params'] ?? static::getContainer()->get(ParameterBagInterface::class),
            $overrides['loggerService'] ?? static::getContainer()->get(LoggerService::class),
        );
    }

    private function setMeasurementProperty(MeasurementService $measurementService, $measurement): void
    {
        $reflection = new \ReflectionClass($measurementService);
        $property = $reflection->getProperty('measurement');
        $property->setValue($measurementService, $measurement);
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
