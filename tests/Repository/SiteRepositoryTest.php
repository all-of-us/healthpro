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
        $organizations = $this->repo->getOrganizations();
        $found = false;
        foreach ($organizations as $organization) {
            if ($organization['organizationId'] === $orgId) {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }
}
