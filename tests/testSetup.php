<?php

namespace App\Tests;

use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Entity\User;
use App\Helper\NphParticipant;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;

class testSetup
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function generateParticipant(string $id = null, string $firstName = null, string $lastName = null,
        \DateTime $dateOfBirth = null): NphParticipant
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
        $participant = new NphParticipant((object)[
            'participantNphId' => $id,
            'DOB' => $dateOfBirth->format('y-m-d'),
            'firstName' => $firstName,
            'lastName' => $lastName
        ]);
        return $participant;

    }

    public function generateNPHOrder(NphParticipant $participant, User $user, SiteService $site): NphOrder
    {
        $nphOrder = new NphOrder();
        $nphOrder->setModule(1);
        $nphOrder->setVisitType('LMT');
        $nphOrder->setTimepoint('preLMT');
        $nphOrder->setOrderId('100000001');
        $nphOrder->setParticipantId($participant->id);
        $nphOrder->setBiobankId('T0000000001');
        $nphOrder->setUser($user);
        $nphOrder->setSite($site->getSiteId());
        $nphOrder->setCreatedTs(new \DateTime());
        $nphOrder->setOrderType('urine');
        $this->em->persist($nphOrder);
        $this->em->flush();

        $nphSample = new NphSample();
        $nphSample->setNphOrder($nphOrder);
        $nphSample->setSampleId('100000002');
        $nphSample->setSampleCode('URINES');
        $nphSample->setSampleGroup('1000000001');
        $this->em->persist($nphSample);
        $this->em->flush();
        return $nphOrder;
    }
}
