<?php

namespace App\Tests;

use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Entity\NphSite;
use App\Entity\Site;
use App\Entity\User;
use App\Helper\Participant;
use Doctrine\ORM\EntityManagerInterface;

class testSetup
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function generateParticipant(string $id = null, string $firstName = null, string $lastName = null, \DateTime $dateOfBirth = null): Participant
    {
        if ($id === null) {
            $id = "P0000001";
        }
        if ($firstName === null) {
            $firstName = "John";
        }
        if ($lastName === null) {
            $lastName = "Doe";
        }
        if ($dateOfBirth === null) {
            $dateOfBirth = new \DateTime('2000-01-01');
        }
        $participant = new Participant((object)[
            'participantId' => $id,
            'dateOfBirth' => $dateOfBirth->format('y-m-d'),
            'firstName' => $firstName,
            'lastName' => $lastName
        ]);
        return $participant;

    }

    public function generateNPHOrder(Participant $participant): NphOrder
    {
        $user = $this->generateUser();
        $siteId = $this->generateSite('hpo')->getGoogleGroup();
        $nphOrder = new NphOrder();
        $nphOrder->setModule(1);
        $nphOrder->setVisitType('LMT');
        $nphOrder->setTimepoint('preLMT');
        $nphOrder->setOrderId('100000001');
        $nphOrder->setParticipantId($participant->id);
        $nphOrder->setUser($user);
        $nphOrder->setSite($siteId);
        $nphOrder->setCreatedTs(new \DateTime());
        $nphOrder->setOrderType('urine');
        $this->em->persist($nphOrder);
        $this->em->flush();

        $nphSample = new NphSample();
        $nphSample->setNphOrder($nphOrder);
        $nphSample->setSampleId('100000002');
        $nphSample->setSampleCode('URINES');
        $this->em->persist($nphSample);
        $this->em->flush();
        return $nphOrder;
    }

    public function generateUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setGoogleId('12345');
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }


    public function generateSite($type = 'hpo'): Site
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
