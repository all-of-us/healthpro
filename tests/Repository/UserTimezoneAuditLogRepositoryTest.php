<?php

namespace App\Tests\Repository;

use App\Entity\User;
use App\Entity\UserTimezoneAuditLog;
use App\Repository\UserTimezoneAuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserTimezoneAuditLogRepositoryTest extends KernelTestCase
{
    private $em;
    private $repo;

    public function setup(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repo = static::getContainer()->get(UserTimezoneAuditLogRepository::class);
    }

    public function testTimezoneChangeIsLogged(): void
    {
        $user = $this->createUser();
        $modifiedTs = new \DateTime('2026-09-10 12:00:00');
        $auditLog = (new UserTimezoneAuditLog())
            ->setUser($user)
            ->setPreviousTimezone('America/New_York')
            ->setCurrentTimezone('America/Chicago')
            ->setClientTimezone('America/Los_Angeles')
            ->setModifiedTs($modifiedTs);
        $this->em->persist($auditLog);
        $this->em->flush();

        $saved = $this->repo->find($auditLog->getId());
        $this->assertNotNull($saved);
        $this->assertSame($user->getId(), $saved->getUser()->getId());
        $this->assertSame('America/New_York', $saved->getPreviousTimezone());
        $this->assertSame('America/Chicago', $saved->getCurrentTimezone());
        $this->assertSame('America/Los_Angeles', $saved->getClientTimezone());
        $this->assertEquals($modifiedTs, $saved->getModifiedTs());
    }

    public function testPreviousTimezoneCanBeNull(): void
    {
        $user = $this->createUser();
        $auditLog = (new UserTimezoneAuditLog())
            ->setUser($user)
            ->setPreviousTimezone(null)
            ->setCurrentTimezone('America/Denver')
            ->setModifiedTs(new \DateTime());
        $this->em->persist($auditLog);
        $this->em->flush();

        $saved = $this->repo->find($auditLog->getId());
        $this->assertNotNull($saved);
        $this->assertNull($saved->getPreviousTimezone());
        $this->assertSame('America/Denver', $saved->getCurrentTimezone());
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('tz-audit-' . uniqid() . '@example.com');
        $user->setGoogleId(uniqid());
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }
}
