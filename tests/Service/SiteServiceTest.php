<?php

namespace App\Tests\Service;

use App\Entity\NphSite;
use App\Entity\Site;
use App\Service\SiteService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;

class SiteServiceTest extends ServiceTestCase
{
    protected $service;
    protected $id;
    protected $userService;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = static::$container->get(SiteService::class);
        $this->userService = static::$container->get(UserService::class);
    }

    public function testCaborConsentDisplay(): void
    {
        $this->id = uniqid();
        $caborSite = 'hpo-site-test' . SiteService::CABOR_STATE . $this->id;
        $nonCaborSite = 'hpo-site-testTN' . $this->id;
        $this->login('test@example.com', [$caborSite, $nonCaborSite]);

        $this->createSite(SiteService::CABOR_STATE);
        $this->service->switchSite($caborSite . '@' . self::GROUP_DOMAIN);
        self::assertTrue($this->service->displayCaborConsent());

        $this->createSite('TN');
        $this->service->switchSite($nonCaborSite . '@' . self::GROUP_DOMAIN);
        self::assertFalse($this->service->displayCaborConsent());
    }

    private function createSite($state): void
    {
        $em = static::$container->get(EntityManagerInterface::class);
        $orgId = 'TEST_ORG_' . $state . $this->id;
        $siteId = 'test' . $state . $this->id;
        $site = new Site();
        $site->setStatus(true)
            ->setName('Test Site ' . $state . $this->id)
            ->setOrganizationId($orgId)
            ->setSiteId($siteId)
            ->setGoogleGroup($siteId)
            ->setWorkqueueDownload('')
            ->setState($state);
        $em->persist($site);
        $em->flush();
    }

    public function testGetNphSiteDisplayName(): void
    {
        $this->id = uniqid();
        $this->createNphSite();
        $this->assertSame('Test Site ' . $this->id, $this->service->getNphSiteDisplayName('test' . $this->id));
    }

    private function createNphSite(): void
    {
        $em = static::$container->get(EntityManagerInterface::class);
        $googleGroup = 'test' . $this->id;
        $site = new NphSite();
        $site->setStatus(true)
            ->setName('Test Site ' . $this->id)
            ->setGoogleGroup($googleGroup);
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
}
