<?php

namespace App\Tests\Service;

use App\Entity\NphSite;
use App\Entity\Site;
use App\Entity\User;
use App\Repository\SiteRepository;
use App\Service\EnvironmentService;
use App\Service\SiteService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SiteServiceTest extends ServiceTestCase
{
    protected $service;
    protected $id;
    protected $userService;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = static::getContainer()->get(SiteService::class);
        $this->userService = static::getContainer()->get(UserService::class);
    }

    public function testCaborConsentDisplay(): void
    {
        $this->id = uniqid();
        $caborSite = 'hpo-site-test' . SiteService::CABOR_STATE . $this->id;
        $nonCaborSite = 'hpo-site-testTN' . $this->id;
        $this->login('test@example.com', [$caborSite, $nonCaborSite]);

        $this->createSite(['state' => SiteService::CABOR_STATE]);
        $this->service->switchSite($caborSite . '@' . self::GROUP_DOMAIN);
        self::assertTrue($this->service->displayCaborConsent());

        $this->createSite(['state' => 'TN']);
        $this->service->switchSite($nonCaborSite . '@' . self::GROUP_DOMAIN);
        self::assertFalse($this->service->displayCaborConsent());
    }

    private function createSite($params = []): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $state = $params['state'] ?? null;
        $orgId = 'TEST_ORG_' . $state . $this->id;
        $siteId = 'test' . $state . $this->id;
        $awardee = 'TEST_AWARDEE_' . $state . $this->id;
        $site = new Site();
        $site->setStatus(true)
            ->setName('Test Site ' . $this->id)
            ->setOrganizationId($orgId)
            ->setSiteId($siteId)
            ->setGoogleGroup($siteId)
            ->setWorkqueueDownload('')
            ->setOrganization($awardee)
            ->setAwardeeId($awardee);
        foreach ($params as $key => $value) {
            $site->{'set' . ucfirst($key)}($value);
        }
        $em->persist($site);
        $em->flush();
    }

    public function testGetNphSiteDisplayName(): void
    {
        $this->id = uniqid();
        $this->createNphSite();
        $this->assertSame('Test Site ' . $this->id, $this->service->getNphSiteDisplayName('test' . $this->id));
    }

    private function createNphSite($params = []): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $googleGroup = 'test' . $this->id;
        $site = new NphSite();
        $site->setStatus(true)
            ->setName('Test Site ' . $this->id)
            ->setGoogleGroup($googleGroup);
        foreach ($params as $key => $value) {
            $site->{'set' . ucfirst($key)}($value);
        }
        $em->persist($site);
        $em->flush();
    }

    public function testNewRoles(): void
    {
        $this->login('test@example.com', ['hpo-site-test@staging.pmi-ops.org', 'awardee-stsi'], 'America/Chicago');
        $totalRoles = $this->userService->getUser()->getRoles();
        $sites[] = (object)[
            'email' => 'test123@staging.pmi-ops.org',
            'name' => 'test',
            'id' => '1234567890'
        ];
        $this->session->set('site', $sites);
        $this->session->remove('awardee');
        $user = $this->userService->getUser();
        $userRoles = $this->userService->getRoles($user->getAllRoles(), $this->requestStack->getSession()->get('site'), $this->requestStack->getSession()->get('awardee'));
        $this->assertSame(['ROLE_USER'], $userRoles);
        $this->service->resetUserRoles();
        $userRoles = $this->userService->getRoles($user->getAllRoles(), $this->requestStack->getSession()->get('site'), $this->requestStack->getSession()->get('awardee'));
        $this->assertSame($totalRoles, $userRoles);
    }

    public function testSiteEntity(): void
    {
        $this->id = uniqid();
        $site = 'hpo-site-test' . $this->id;
        $this->login('test@example.com', [$site]);

        $this->createSite();
        $this->service->switchSite($site . '@' . self::GROUP_DOMAIN);
        self::assertSame('TEST_AWARDEE_' . $this->id, $this->service->getSiteAwardee());
        self::assertSame('TEST_ORG_' . $this->id, $this->service->getSiteOrganization());
        self::assertSame('TEST_AWARDEE_' . $this->id, $this->service->getSiteAwardeeId());
    }

    /**
     * @dataProvider siteDataProvider
     */
    public function testIsValidSite($program, $mayoLinkAccount, $switchSiteName, $checkSiteName, $expectedResult): void
    {
        // set env to stable to test isValidSite status
        $environmentService = $this->createMock(EnvironmentService::class);
        $environmentService->method('isStable')->willReturn(true);
        $this->service = new SiteService(
            static::getContainer()->get(ParameterBagInterface::class),
            static::getContainer()->get(RequestStack::class),
            static::getContainer()->get(EntityManagerInterface::class),
            static::getContainer()->get(UserService::class),
            $environmentService,
            static::getContainer()->get(TokenStorageInterface::class),
        );
        $this->id = uniqid();
        $site = $switchSiteName . $this->id;
        $switchSiteEmail = $site . '@' . self::GROUP_DOMAIN;
        $this->login('test@example.com', [$site]);
        $this->service->switchSite($switchSiteEmail);
        if ($program == User::PROGRAM_NPH) {
            $this->createNphSite(['mayolinkAccount' => $mayoLinkAccount]);
        } else {
            $this->createSite(['mayolinkAccount' => $mayoLinkAccount]);
        }
        $checkSiteEmail = $checkSiteName . $this->id . '@' . self::GROUP_DOMAIN;
        $this->session->set('program', $program);
        $this->assertEquals($expectedResult, $this->service->isValidSite($checkSiteEmail));
    }

    public function siteDataProvider(): array
    {
        return [
            'valid mayolink account number and site email' => [User::PROGRAM_HPO, '123456789', 'hpo-site-test', 'hpo-site-test', true],
            'no mayolink account number and with site email' => [User::PROGRAM_HPO, null, 'hpo-site-test', 'hpo-site-test', false],
            'no mayolink account number and with invalid site email' => [User::PROGRAM_HPO, null, 'hpo-site-test', 'hpo-site-test-2', false],
            'nph valid mayolink account number and site email' => [User::PROGRAM_NPH, '123456789', 'nph-site-test', 'nph-site-test', true],
            'nph no mayolink account number and with site email' => [User::PROGRAM_NPH, null, 'nph-site-test', 'nph-site-test', false],
            'nph no mayolink account number and with invalid site email' => [User::PROGRAM_NPH, null, 'nph-site-test', 'nph-site-test-2', false]
        ];
    }

    /**
     * @dataProvider siteStatusProvider
     */
    public function testIsActiveSite($activeSiteCount, $expectedResult): void
    {
        $siteId = 'test-123456'; // Replace with an actual site ID

        $repositoryMock = $this->createMock(SiteRepository::class);
        $repositoryMock->expects($this->once())
            ->method('getActiveSiteCount')
            ->with($siteId)
            ->willReturn($activeSiteCount);

        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock->expects($this->once())
            ->method('getRepository')
            ->with(Site::class)
            ->willReturn($repositoryMock);

        $siteService = new SiteService(
            static::getContainer()->get(ParameterBagInterface::class),
            static::getContainer()->get(RequestStack::class),
            $entityManagerMock,
            static::getContainer()->get(UserService::class),
            static::getContainer()->get(EnvironmentService::class),
            static::getContainer()->get(TokenStorageInterface::class),
        );

        $result = $siteService->isActiveSite($siteId);
        $this->assertSame($expectedResult, $result);
    }

    public function siteStatusProvider(): array
    {
        return [
            'Active site' => [1, true],
            'Inactive site' => [0, false],
        ];
    }
}
