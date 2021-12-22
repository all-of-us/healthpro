<?php

namespace App\Tests\Service;

use App\Entity\Site;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;

class SiteServiceTest extends ServiceTestCase
{
    protected $service;
    protected $id;

    public function testCaborConsentDisplay(): void
    {
        $this->id = uniqid();
        $site = 'hpo-site-test' . $this->id;
        $this->login('test@example.com', [$site]);
        $this->createSite();
        $this->service = static::$container->get(SiteService::class);
        $this->service->switchSite($site . '@' . self::GROUP_DOMAIN);
        self::assertFalse($this->service->displayCaborConsent());
    }

    public function createSite(): void
    {
        $em = static::$container->get(EntityManagerInterface::class);
        $orgId = 'TEST_ORG_' . $this->id;
        $siteId = 'test' . $this->id;
        $site = new Site();
        $site->setStatus(true)
            ->setName('Test Site ' . $this->id)
            ->setOrganizationId($orgId)
            ->setSiteId($siteId)
            ->setGoogleGroup($siteId)
            ->setWorkqueueDownload('')
            ->setState('CA');
        $em->persist($site);
        $em->flush();
    }
}
