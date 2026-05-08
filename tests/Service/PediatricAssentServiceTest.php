<?php

namespace App\Tests\Service;

use App\Entity\PediatricAssent;
use App\Entity\User;
use App\Security\User as SecurityUser;
use App\Service\PediatricAssentService;
use App\Service\SiteService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;

class PediatricAssentServiceTest extends TestCase
{
    public function testSubmitMeasurementAssentPersistsNewAssent(): void
    {
        $user = $this->createUser('tester@example.com', 'America/Chicago');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userService = $this->createMock(UserService::class);
        $siteService = $this->createMock(SiteService::class);

        $userService->expects($this->once())->method('getUserEntity')->willReturn($user);
        $userService->expects($this->never())->method('getUser');
        $siteService->expects($this->once())->method('getSiteId')->willReturn('hpo-site-test');
        $entityManager->expects($this->never())->method('getRepository');
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($assent) use ($user) {
                self::assertInstanceOf(PediatricAssent::class, $assent);
                self::assertSame('P123456789', $assent->getParticipantId());
                self::assertSame($user, $assent->getUser());
                self::assertSame('tester@example.com', $assent->getCreatedBy());
                self::assertSame('hpo-site-test', $assent->getSite());
                self::assertSame(PediatricAssent::TYPE_PHYSICAL_MEASUREMENT, $assent->getAssentType());
                self::assertSame(PediatricAssent::RESPONSE_YES, $assent->getAssentResponse());
                self::assertSame(3, $assent->getCreatedTimezoneId());
                self::assertSame(PediatricAssent::API_STATUS_PENDING, $assent->getApiStatus());
                self::assertNull($assent->getApiAssentId());
                self::assertNull($assent->getApiError());
                self::assertInstanceOf(\DateTimeInterface::class, $assent->getCreatedTs());

                return true;
            }));
        $entityManager->expects($this->once())->method('flush');

        $service = new PediatricAssentService($entityManager, $userService, $siteService);
        $result = $service->submitMeasurementAssent('P123456789', 'yes');

        self::assertTrue($result['success']);
        self::assertArrayHasKey('assent', $result);
        self::assertInstanceOf(PediatricAssent::class, $result['assent']);
    }

    public function testSubmitOrderAssentMapsSelectionToSampleType(): void
    {
        $user = $this->createUser('tester@example.com');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userService = $this->createMock(UserService::class);
        $siteService = $this->createMock(SiteService::class);

        $userService->method('getUserEntity')->willReturn($user);
        $siteService->method('getSiteId')->willReturn('hpo-site-test');
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($assent) {
                self::assertInstanceOf(PediatricAssent::class, $assent);
                self::assertSame(PediatricAssent::TYPE_URINE_SAMPLE, $assent->getAssentType());
                self::assertSame(PediatricAssent::RESPONSE_UNABLE_TO_ASSENT, $assent->getAssentResponse());

                return true;
            }));
        $entityManager->expects($this->once())->method('flush');

        $service = new PediatricAssentService($entityManager, $userService, $siteService);
        $result = $service->submitOrderAssent('P123456789', 'urine', 'unable');

        self::assertTrue($result['success']);
        self::assertSame(PediatricAssent::TYPE_URINE_SAMPLE, $result['assent']->getAssentType());
    }

    public function testSubmitOrderAssentReturnsErrorForInvalidSelection(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userService = $this->createMock(UserService::class);
        $siteService = $this->createMock(SiteService::class);

        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $service = new PediatricAssentService($entityManager, $userService, $siteService);
        $result = $service->submitOrderAssent('P123456789', 'hair', 'yes');

        self::assertFalse($result['success']);
        self::assertSame('Invalid pediatric assent type.', $result['errorMessage']);
    }

    public function testSubmitMeasurementAssentReturnsErrorForInvalidResponse(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userService = $this->createMock(UserService::class);
        $siteService = $this->createMock(SiteService::class);

        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $service = new PediatricAssentService($entityManager, $userService, $siteService);
        $result = $service->submitMeasurementAssent('P123456789', 'maybe');

        self::assertFalse($result['success']);
        self::assertSame('Invalid pediatric assent response.', $result['errorMessage']);
    }

    public function testSubmitMeasurementAssentUsesSecurityUserEmailWhenEntityIsMissing(): void
    {
        $securityUser = $this->createMock(SecurityUser::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userService = $this->createMock(UserService::class);
        $siteService = $this->createMock(SiteService::class);

        $securityUser->expects($this->once())->method('getEmail')->willReturn('fallback@example.com');
        $userService->expects($this->once())->method('getUserEntity')->willReturn(null);
        $userService->expects($this->once())->method('getUser')->willReturn($securityUser);
        $siteService->expects($this->once())->method('getSiteId')->willReturn('hpo-site-test');
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($assent) {
                self::assertInstanceOf(PediatricAssent::class, $assent);
                self::assertNull($assent->getUser());
                self::assertSame('fallback@example.com', $assent->getCreatedBy());
                self::assertSame(2, $assent->getCreatedTimezoneId());

                return true;
            }));
        $entityManager->expects($this->once())->method('flush');

        $service = new PediatricAssentService($entityManager, $userService, $siteService);
        $result = $service->submitMeasurementAssent('P123456789', 'no');

        self::assertTrue($result['success']);
    }

    public function testSubmitMeasurementAssentReturnsErrorWhenUserCannotBeResolved(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userService = $this->createMock(UserService::class);
        $siteService = $this->createMock(SiteService::class);

        $userService->expects($this->once())->method('getUserEntity')->willReturn(null);
        $userService->expects($this->once())->method('getUser')->willReturn(null);
        $siteService->expects($this->never())->method('getSiteId');
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $service = new PediatricAssentService($entityManager, $userService, $siteService);
        $result = $service->submitMeasurementAssent('P123456789', 'yes');

        self::assertFalse($result['success']);
        self::assertSame('Unable to determine the current user for pediatric assent.', $result['errorMessage']);
    }

    public function testSubmitMeasurementAssentReturnsErrorWhenSiteCannotBeResolved(): void
    {
        $user = $this->createUser('tester@example.com');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userService = $this->createMock(UserService::class);
        $siteService = $this->createMock(SiteService::class);

        $userService->expects($this->once())->method('getUserEntity')->willReturn($user);
        $userService->expects($this->never())->method('getUser');
        $siteService->expects($this->once())->method('getSiteId')->willReturn(null);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $service = new PediatricAssentService($entityManager, $userService, $siteService);
        $result = $service->submitMeasurementAssent('P123456789', 'yes');

        self::assertFalse($result['success']);
        self::assertSame('Unable to determine the current site for pediatric assent.', $result['errorMessage']);
    }

    public function testSubmitMeasurementAssentReturnsExistingCreatedAssentWithoutPersisting(): void
    {
        $user = $this->createUser('tester@example.com');
        $existingAssent = (new PediatricAssent())
            ->setParticipantId('P123456789')
            ->setAssentType(PediatricAssent::TYPE_PHYSICAL_MEASUREMENT)
            ->setAssentResponse(PediatricAssent::RESPONSE_YES)
            ->setApiStatus(PediatricAssent::API_STATUS_CREATED);
        $repository = $this->createMock(ObjectRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userService = $this->createMock(UserService::class);
        $siteService = $this->createMock(SiteService::class);

        $userService->method('getUserEntity')->willReturn($user);
        $siteService->method('getSiteId')->willReturn('hpo-site-test');
        $repository->expects($this->once())->method('find')->with(42)->willReturn($existingAssent);
        $entityManager->expects($this->once())->method('getRepository')->with(PediatricAssent::class)->willReturn($repository);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $service = new PediatricAssentService($entityManager, $userService, $siteService);
        $result = $service->submitMeasurementAssent('P123456789', 'yes', 42);

        self::assertTrue($result['success']);
        self::assertSame($existingAssent, $result['assent']);
    }

    public function testSubmitMeasurementAssentReusesExistingPendingAssentAndResetsApiFields(): void
    {
        $user = $this->createUser('tester@example.com');
        $existingAssent = (new PediatricAssent())
            ->setParticipantId('P123456789')
            ->setAssentType(PediatricAssent::TYPE_PHYSICAL_MEASUREMENT)
            ->setAssentResponse(PediatricAssent::RESPONSE_NO)
            ->setApiStatus(PediatricAssent::API_STATUS_FAILED)
            ->setApiAssentId('A12345')
            ->setApiError('previous failure');
        $repository = $this->createMock(ObjectRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userService = $this->createMock(UserService::class);
        $siteService = $this->createMock(SiteService::class);

        $userService->method('getUserEntity')->willReturn($user);
        $siteService->method('getSiteId')->willReturn('hpo-site-test');
        $repository->expects($this->once())->method('find')->with(7)->willReturn($existingAssent);
        $entityManager->expects($this->once())->method('getRepository')->with(PediatricAssent::class)->willReturn($repository);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($assent) use ($existingAssent) {
                self::assertSame($existingAssent, $assent);
                self::assertSame(PediatricAssent::API_STATUS_PENDING, $assent->getApiStatus());
                self::assertNull($assent->getApiAssentId());
                self::assertNull($assent->getApiError());

                return true;
            }));
        $entityManager->expects($this->once())->method('flush');

        $service = new PediatricAssentService($entityManager, $userService, $siteService);
        $result = $service->submitMeasurementAssent('P123456789', 'no', 7);

        self::assertTrue($result['success']);
        self::assertSame($existingAssent, $result['assent']);
    }

    public function testBuildPediatricAssentPayload(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userService = $this->createMock(UserService::class);
        $siteService = $this->createMock(SiteService::class);
        $service = new PediatricAssentService($entityManager, $userService, $siteService);
        $assent = (new PediatricAssent())
            ->setParticipantId('P123456789')
            ->setCreatedBy('tester@example.com')
            ->setSite('hpo-site-test')
            ->setAssentType(PediatricAssent::TYPE_BLOOD_SAMPLE)
            ->setAssentResponse(PediatricAssent::RESPONSE_YES)
            ->setCreatedTs(new \DateTimeImmutable('2026-05-07 10:15:00', new \DateTimeZone('America/Chicago')))
            ->setCreatedTimezoneId(3)
            ->setApiStatus(PediatricAssent::API_STATUS_PENDING);

        $payload = $service->buildPediatricAssentPayload($assent);

        self::assertSame('P123456789', $payload->participantId);
        self::assertSame('tester@example.com', $payload->createdBy);
        self::assertSame('hpo-site-test', $payload->site);
        self::assertSame(PediatricAssent::TYPE_BLOOD_SAMPLE, $payload->assentType);
        self::assertSame(PediatricAssent::RESPONSE_YES, $payload->assentResponse);
        self::assertSame('2026-05-07T15:15:00Z', $payload->created);
    }

    private function createUser(string $email, ?string $timezone = 'America/New_York'): User
    {
        return (new User())
            ->setEmail($email)
            ->setGoogleId('google-id')
            ->setTimezone($timezone);
    }
}
