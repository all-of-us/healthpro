<?php

namespace App\Tests\Service;

use App\Entity\Site;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SiteRepositoryTest extends KernelTestCase
{
    private $em;
    private $repo;

    public function setup(): void
    {
        self::bootKernel();
        $this->em = static::$container->get(EntityManagerInterface::class);
        $this->repo = static::$container->get(SiteRepository::class);

    }

    public function testGetOrganizations(): void
    {
        $id = uniqid();
        $orgId = 'TEST_ORG_' . $id;
        $siteId = 'test-'  . $id;
        $site = new Site();
        $site->setStatus(true)
            ->setName('Test Site ' . $id)
            ->setOrganizationId($orgId)
            ->setSiteId($siteId)
            ->setGoogleGroup($siteId)
            ->setWorkqueueDownload('');
        $this->em->persist($site);
        $this->em->flush();

        $found = false;
        foreach ($this->repo->getOrganizations() as $organization) {
            if ($organization['organizationId'] === $orgId) {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    public function testGetAwardees(): void
    {
        $id = uniqid();
        $awardeeId = 'TEST_AWARDEE_' . $id;
        $siteId = 'test-'  . $id;
        $site = new Site();
        $site->setStatus(true)
            ->setName('Test Site ' . $id)
            ->setAwardeeId($awardeeId)
            ->setSiteId($siteId)
            ->setGoogleGroup($siteId)
            ->setWorkqueueDownload('');
        $this->em->persist($site);
        $this->em->flush();

        $found = false;
        foreach ($this->repo->getAwardees() as $awardee) {
            if ($awardee['awardeeId'] === $awardeeId) {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }
}
