<?php

namespace App\Tests\Service;

use App\Entity\Measurement;
use App\Entity\Site;
use App\Form\SiteType;
use App\Helper\PpscParticipant;
use App\Repository\MeasurementRepository;
use App\Service\LoggerService;
use App\Service\MeasurementService;
use App\Service\Ppsc\PpscApiService;
use App\Service\RdrApiService;
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
        $this->siteService = static::$container->get(SiteService::class);
        $this->measurementService = static::$container->get(MeasurementService::class);
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

    private function createSite($hybrid = null): void
    {
        $em = static::$container->get(EntityManagerInterface::class);
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
    public function testBackfillMeasurementsSexAtBirth($participantData, $expectsSetSexAtBirth, $expectsPersist)
    {
        $measurement = $this->createMock(Measurement::class);
        $measurement->method('getParticipantId')->willReturn('123');

        $repository = $this->createMock(MeasurementRepository::class);
        $repository->method('getMissingSexAtBirthPediatricMeasurements')->willReturn([$measurement]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $ppscApiService = $this->createMock(PpscApiService::class);
        $ppscApiService->method('getParticipantById')->willReturn($participantData);

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

        $entityManager->expects($this->once())->method('flush');

        $measurementService = new MeasurementService(
            $entityManager,
            static::getContainer()->get(RequestStack::class),
            static::getContainer()->get(UserService::class),
            $ppscApiService,
            static::getContainer()->get(SiteService::class),
            static::getContainer()->get(ParameterBagInterface::class),
            static::getContainer()->get(LoggerService::class)
        );

        $measurementService->backfillMeasurementsSexAtBirth();
    }

    public function backfillMeasurementsProvider(): array
    {
        return [
            'Valid sexAtBirth data' => [
                new PpscParticipant((object) ['sexAtBirth' => 1]),
                true,  // Expects setSexAtBirth
                true   // Expects persist
            ],
            'Null participant data' => [
                null,
                false,
                false
            ],
        ];
    }


    /**
     * @dataProvider pediatricAssentCheckProvider
     */
    public function testRequirePediatricAssentCheck(bool $isPediatric, string $dob, bool $expected)
    {
        $participant = new PpscParticipant((object) [
            'isPediatric' => $isPediatric,
            'dob' => $dob
        ]);
        $this->assertSame($expected, $this->measurementService->requirePediatricAssentCheck($participant));
    }

    public function pediatricAssentCheckProvider(): array
    {
        $today = new \DateTime();
        return [
            'Not pediatric' => [false, (clone $today)->sub(new \DateInterval('P90M'))->format('Y-m-d'), false],
            'Pediatric, too young' => [true, (clone $today)->sub(new \DateInterval('P83M'))->format('Y-m-d'), false],
            'Pediatric, just old enough' => [true, (clone $today)->sub(new \DateInterval('P84M'))->format('Y-m-d'), true],
            'Pediatric, in range' => [true, (clone $today)->sub(new \DateInterval('P100M'))->format('Y-m-d'), true],
            'Pediatric, almost too old' => [true, (clone $today)->sub(new \DateInterval('P143M'))->format('Y-m-d'), true],
            'Pediatric, too old' => [true, (clone $today)->sub(new \DateInterval('P144M'))->format('Y-m-d'), false],
        ];
    }

    /**
     * @dataProvider getMeasurementUrlProvider
     */
    public function testGetMeasurementUrl(bool $isPediatric, string $dob, bool $bloodDonorCheck, string $expectedUrl)
    {
        $measurementServiceMock = $this->getMockBuilder(MeasurementService::class)
            ->setConstructorArgs([
                $this->createMock(EntityManagerInterface::class),
                $this->createMock(RequestStack::class),
                $this->createMock(UserService::class),
                $this->createMock(PpscApiService::class),
                $this->createMock(SiteService::class),
                $this->createMock(ParameterBagInterface::class),
                $this->createMock(LoggerService::class),
            ])
            ->onlyMethods(['requireBloodDonorCheck'])
            ->getMock();

        $measurementServiceMock->method('requireBloodDonorCheck')->willReturn($bloodDonorCheck);

        $participant = new PpscParticipant((object) [
            'isPediatric' => $isPediatric,
            'dob' => $dob
        ]);

        $this->assertSame($expectedUrl, $measurementServiceMock->getMeasurementUrl($participant));
    }

    public function getMeasurementUrlProvider(): array
    {
        $today = new \DateTime();
        return [
            'Pediatric assent check required' => [true, (clone $today)->sub(new \DateInterval('P90M'))->format('Y-m-d'), false, 'measurement_pediatric_assent_check'],
            'Blood donor check required' => [false, (clone $today)->sub(new \DateInterval('P90M'))->format('Y-m-d'), true, 'measurement_blood_donor_check'],
            'Standard measurement' => [false, (clone $today)->sub(new \DateInterval('P90M'))->format('Y-m-d'), false, 'measurement'],
            'Pediatric but not in assent age range, blood donor check' => [true, (clone $today)->sub(new \DateInterval('P80M'))->format('Y-m-d'), true, 'measurement_blood_donor_check'],
            'Pediatric but not in assent age range, standard' => [true, (clone $today)->sub(new \DateInterval('P80M'))->format('Y-m-d'), false, 'measurement'],
        ];
    }

}
