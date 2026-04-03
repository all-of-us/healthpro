<?php

namespace App\Tests\Repository;

use App\Entity\NphSite;
use App\Entity\Site;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RepositoryTestCase extends KernelTestCase
{
    protected $em;

    public function setup(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function getUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setGoogleId('12345');
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    protected function getSite($type = 'hpo')
    {
        $id = uniqid();
        $orgId = 'TEST_ORG_' . $id;
        $siteId = 'test-' . $id;
        if ($type === 'nph') {
            $site = new NphSite();
            $site->setStatus(true)
                ->setName('Test Site ' . $id)
                ->setOrganizationId($orgId)
                ->setGoogleGroup($siteId);
        } else {
            $site = new Site();
            $site->setStatus(true)
                ->setName('Test Site ' . $id)
                ->setOrganizationId($orgId)
                ->setSiteId($siteId)
                ->setGoogleGroup($siteId)
                ->setWorkqueueDownload('');
        }
        $this->em->persist($site);
        $this->em->flush();
        return $site;
    }
}
