<?php

namespace App\Tests;

use App\Entity\NphDlw;
use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Entity\User;
use App\Helper\NphParticipant;
use App\Helper\Participant;
use App\Service\SiteService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;

class testSetup
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function generateNphParticipant(
        string $id = null,
        string $firstName = null,
        string $lastName = null,
        \DateTime $dateOfBirth = null
    ): NphParticipant {
        if ($id === null) {
            $id = 'P0000001';
        }
        if ($firstName === null) {
            $firstName = 'John';
        }
        if ($lastName === null) {
            $lastName = 'Doe';
        }
        if ($dateOfBirth === null) {
            $dateOfBirth = new \DateTime('2000-01-01');
        }
        $participant = new NphParticipant((object) [
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
        $nphOrder->setDowntimeGenerated(false);
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

    public function generateParticipant(
        string $id = null,
        string $firstName = null,
        string $lastName = null,
        \DateTime $dateOfBirth = null,
        array $rdrData = []
    ): Participant {
        if ($id === null) {
            $id = 'P0000000001';
        }
        if ($firstName === null) {
            $firstName = 'John';
        }
        if ($lastName === null) {
            $lastName = 'Doe';
        }
        if ($dateOfBirth === null) {
            $dateOfBirth = new \DateTime('2000-01-01');
        }
        $participantInfo = array_merge($rdrData, [
            'participantId' => $id,
            'DOB' => $dateOfBirth->format('y-m-d'),
            'firstName' => $firstName,
            'lastName' => $lastName,
        ]);
        return new Participant((object) $participantInfo);
    }

    public function generateNphDlw(
        User $user,
        string $participantId = null,
        string $module = null,
        string $visit = null,
        \DateTime $doseAdministered = null,
        float $actualDose = null,
        float $participantWeight = null,
        string $doseBatchId = null,
        \DateTime $dateModified = null,
        int $modifiedTimezoneId = null,
    ): NphDlw
    {
        if ($participantId === null) {
            $participantId = 'P0000000001';
        }
        if ($module === null) {
            $module = '3';
        }
        if ($visit === null) {
            $visit = 'OrangeDiet';
        }
        if ($doseAdministered === null) {
            $doseAdministered = new \DateTime('2000-01-01');
        }
        if ($participantWeight === null) {
            $participantWeight = 100.2;
        }
        if ($doseBatchId === null) {
            $doseBatchId = 'B0000000001';
        }
        if ($actualDose === null) {
            $actualDose = 15.5;
        }
        if ($dateModified === null) {
            $dateModified = new \DateTime('2000-01-01');
        }
        if ($modifiedTimezoneId === null) {
            $modifiedTimezoneId = 3;
        }
        $dlw = new NphDlw();
        $dlw->setDoseAdministered($doseAdministered);
        $dlw->setModule($module);
        $dlw->setVisit($visit);
        $dlw->setParticipantWeight($participantWeight);
        $dlw->setActualDose($actualDose);
        $dlw->setNphParticipant($participantId);
        $dlw->setDoseBatchId($doseBatchId);
        $dlw->setModifiedTs($dateModified);
        $dlw->setModifiedTimezoneId($modifiedTimezoneId);
        $dlw->setUser($user);
        $this->em->persist($dlw);
        $this->em->flush();
        return $dlw;
    }
}
