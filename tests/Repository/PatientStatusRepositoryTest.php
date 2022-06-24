<?php

namespace App\Tests\Repository;

use App\Entity\PatientStatus;
use App\Entity\PatientStatusHistory;
use App\Entity\User;
use App\Repository\PatientStatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PatientStatusRepositoryTest extends KernelTestCase
{
    private $em;
    private $repo;

    public function setup(): void
    {
        self::bootKernel();
        $this->em = static::$container->get(EntityManagerInterface::class);
        $this->repo = static::$container->get(PatientStatusRepository::class);

    }

    /**
     * @dataProvider paginationDataProvider
     */
    public function testOnsitePatientStatusPagination($start, $length, $resultCount, $resultParticipantId): void
    {
        $this->createPatientStatus();
        $params = [];
        $params['start'] = $start;
        $params['length'] = $length;
        $patientStatuses = $this->repo->getOnsitePatientStatuses('PS_AWARDEE_TEST', $params);
        $this->assertEquals($resultCount, count($patientStatuses));
        $this->assertEquals($resultParticipantId, $patientStatuses[0]['participantId']);

    }

    public function paginationDataProvider()
    {
        return [
            [0, 2, 2, 'P000000000'],
            [1, 1, 1, 'P000000001'],
            [3, 1, 1, 'P000000003']
        ];
    }

    private function createPatientStatus(): void
    {
        $userId = $this->getUser()->getId();
        foreach ($this->getPatientStatusData() as $patientStatusData) {
            $patientStatus = new PatientStatus();
            $patientStatus->setParticipantId($patientStatusData['participantId'])
                ->setOrganization($patientStatusData['organization'])
                ->setAwardee($patientStatusData['awardee']);
            $this->em->persist($patientStatus);

            $patientStatusHistory = new PatientStatusHistory();
            $patientStatusHistory->setPatientStatus($patientStatus);
            $patientStatusHistory->setUserId($userId);
            $patientStatusHistory->setSite($patientStatusData['site']);
            $patientStatusHistory->setStatus($patientStatusData['status']);
            $patientStatusHistory->setComments($patientStatusData['comments']);
            $patientStatusHistory->setCreatedTs($patientStatusData['createdTs']);
            $patientStatusHistory->setRdrTs($patientStatusData['rdrTs']);
            $this->em->persist($patientStatusHistory);

            $patientStatus->setHistory($patientStatusHistory);
            $this->em->persist($patientStatus);
            $this->em->flush();
        }
    }

    private function getUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setGoogleId('12345');
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    private function getPatientStatusData(): array
    {
        $now =  new \DateTime();
        return [
            [
                'participantId' => 'P000000000',
                'organization' => 'PS_ORG_TEST',
                'awardee' => 'PS_AWARDEE_TEST',
                'site' => 'PS_SITE_TEST',
                'status' => 'YES',
                'comments' => 'test1',
                'createdTs' => $now,
                'rdrTs' => $now
            ],
            [
                'participantId' => 'P000000001',
                'organization' => 'PS_ORG_TEST',
                'awardee' => 'PS_AWARDEE_TEST',
                'site' => 'PS_SITE_TEST',
                'status' => 'NO',
                'comments' => 'test2',
                'createdTs' => $now,
                'rdrTs' => $now
            ],
            [
                'participantId' => 'P000000002',
                'organization' => 'PS_ORG_TEST',
                'awardee' => 'PS_AWARDEE_TEST',
                'site' => 'PS_SITE_TEST',
                'status' => 'YES',
                'comments' => 'test3',
                'createdTs' => $now,
                'rdrTs' => $now
            ],
            [
                'participantId' => 'P000000003',
                'organization' => 'PS_ORG_TEST',
                'awardee' => 'PS_AWARDEE_TEST',
                'site' => 'PS_SITE_TEST',
                'status' => 'UNKNOWN',
                'comments' => 'test4',
                'createdTs' => $now,
                'rdrTs' => $now
            ],
            [
                'participantId' => 'P000000004',
                'organization' => 'PS_ORG_TEST',
                'awardee' => 'PS_AWARDEE_TEST',
                'site' => 'PS_SITE_TEST',
                'status' => 'NO_ACCESS',
                'comments' => 'test5',
                'createdTs' => $now,
                'rdrTs' => $now
            ],
        ];
    }
}
