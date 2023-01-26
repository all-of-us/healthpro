<?php

namespace App\Tests\Service;

use App\Entity\NphSite;
use App\Entity\Site;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;

class SiteServiceTest extends ServiceTestCase
{
    protected $service;
    protected $id;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = static::$container->get(SiteService::class);
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
}
