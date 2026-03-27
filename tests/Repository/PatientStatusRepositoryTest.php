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
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repo = static::getContainer()->get(PatientStatusRepository::class);
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
            [0, 2, 2, 'P000000004'],
            [1, 1, 1, 'P000000003'],
            [3, 1, 1, 'P000000001']
        ];
    }

    /**
     * @dataProvider dateFilterDataProvider
     */
    public function testOnsitePatientStatusDateFilters($startDate, $endDate, $resultCount): void
    {
        $this->createPatientStatus();
        $params = [];
        $params['startDate'] = $this->getDate($startDate);
        $params['endDate'] = $this->getDate($endDate);
        $patientStatuses = $this->repo->getOnsitePatientStatuses('PS_AWARDEE_TEST', $params);
        $this->assertEquals($resultCount, count($patientStatuses));
    }

    public function dateFilterDataProvider()
    {
        return [
            ['2022-01-15', '2022-02-15', 2],
            ['2022-01-15', '2022-03-15', 3],
            ['2022-01-15', '2022-05-15', 5],
            ['2022-06-15', '2022-07-15', 0],
            ['2022-02-15', '', 4],
            ['', '2022-05-15', 5]
        ];
    }

    /**
     * @dataProvider participantIdDataProvider
     */
    public function testOnsitePatientStatusParticipantIdLookup($participantId): void
    {
        $this->createPatientStatus();
        $params = [];
        $params['participantId'] = $participantId;
        $patientStatuses = $this->repo->getOnsitePatientStatuses('PS_AWARDEE_TEST', $params);
        $this->assertEquals($participantId, $patientStatuses[0]['participantId']);
    }

    public function participantIdDataProvider()
    {
        return [
            ['P000000000'],
            ['P000000001'],
            ['P000000002'],
            ['P000000003'],
            ['P000000004']
        ];
    }

    public function testGetOnsitePatientStatusSites(): void
    {
        $this->createPatientStatus();
        $sites = $this->repo->getOnsitePatientStatusSites('PS_AWARDEE_TEST');
        $this->assertCount(1, $sites);
    }

    /**
     * @dataProvider paramsCountDataProvider
     */
    public function testGetOnsitePatientStatusesCount($params, $resultCount): void
    {
        $this->createPatientStatus();
        $count = $this->repo->getOnsitePatientStatusesCount('PS_AWARDEE_TEST', $params);
        $this->assertEquals($resultCount, $count);
    }

    public function paramsCountDataProvider()
    {
        return [
            [[], 5],
            [['participantId' => 'P000000001'], 1],
            [['startDate' => $this->getDate('2022-03-15')], 3],
            [['endDate' => $this->getDate('2022-04-15')], 4],
            [['startDate' => $this->getDate('2022-02-15'), 'endDate' => $this->getDate('2022-04-15')], 3],
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
            $patientStatusHistory->setCreatedTs(new \DateTime($patientStatusData['createdTs']));
            $patientStatusHistory->setRdrTs(new \DateTime($patientStatusData['rdrTs']));
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
        return [
            [
                'participantId' => 'P000000000',
                'organization' => 'PS_ORG_TEST',
                'awardee' => 'PS_AWARDEE_TEST',
                'site' => 'PS_SITE_TEST',
                'status' => 'YES',
                'comments' => 'test1',
                'createdTs' => '2022-01-15',
                'rdrTs' => '2022-01-15'
            ],
            [
                'participantId' => 'P000000001',
                'organization' => 'PS_ORG_TEST',
                'awardee' => 'PS_AWARDEE_TEST',
                'site' => 'PS_SITE_TEST',
                'status' => 'NO',
                'comments' => 'test2',
                'createdTs' => '2022-02-15',
                'rdrTs' => '2022-02-15'
            ],
            [
                'participantId' => 'P000000002',
                'organization' => 'PS_ORG_TEST',
                'awardee' => 'PS_AWARDEE_TEST',
                'site' => 'PS_SITE_TEST',
                'status' => 'YES',
                'comments' => 'test3',
                'createdTs' => '2022-03-15',
                'rdrTs' => '2022-03-15'
            ],
            [
                'participantId' => 'P000000003',
                'organization' => 'PS_ORG_TEST',
                'awardee' => 'PS_AWARDEE_TEST',
                'site' => 'PS_SITE_TEST',
                'status' => 'UNKNOWN',
                'comments' => 'test4',
                'createdTs' => '2022-04-15',
                'rdrTs' => '2022-04-15'
            ],
            [
                'participantId' => 'P000000004',
                'organization' => 'PS_ORG_TEST',
                'awardee' => 'PS_AWARDEE_TEST',
                'site' => 'PS_SITE_TEST',
                'status' => 'NO_ACCESS',
                'comments' => 'test5',
                'createdTs' => '2022-05-15',
                'rdrTs' => '2022-05-15'
            ],
        ];
    }

    private function getDate($date): ?\DateTime
    {
        return $date ? new \DateTime($date) : null;
    }
}
