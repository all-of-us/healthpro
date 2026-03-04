<?php

namespace App\Tests\Repository;

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
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repo = static::getContainer()->get(SiteRepository::class);

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

    public function testIncreaseGroupConcatMaxLength(): void
    {
        $this->repo->increaseGroupConcatMaxLength();
        $sql = "SELECT @@group_concat_max_len AS group_concat_max_len";
        $result = $this->em->getConnection()->executeQuery($sql)->fetchOne();
        $this->assertSame(100000, $result);
    }

    /**
     * @dataProvider siteStatusProvider
     */
    public function testActiveSiteCount($siteId, $status, $expectedResult): void
    {
        $awardeeId = 'TEST_AWARDEE_' . $siteId;
        $siteId = 'test-'  . $siteId;
        $site = new Site();
        $site->setStatus($status)
            ->setName('Test Site ' . $siteId)
            ->setAwardeeId($awardeeId)
            ->setSiteId($siteId)
            ->setGoogleGroup($siteId)
            ->setWorkqueueDownload('');
        $this->em->persist($site);
        $this->em->flush();
        $this->assertSame($expectedResult, $this->repo->getActiveSiteCount($siteId));
    }

    public function siteStatusProvider(): array
    {
        return [
            'Active site' => ['test-site12345', 1, 1],
            'Inactive site' => ['test2-site12345', 0, 0],
        ];
    }
}
