<?php

namespace App\Tests\Repository;

use App\Entity\IdVerification;
use App\Entity\User;
use App\Repository\IdVerificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IdVerificationRepositoryTest extends KernelTestCase
{
    private $em;
    private $repo;

    public function setup(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repo = static::getContainer()->get(IdVerificationRepository::class);

    }

    /**
     * @dataProvider paginationDataProvider
     */
    public function testOnsiteIdVerificationsPagination($start, $length, $resultCount, $resultParticipantId): void
    {
        $this->createIdVerifications();
        $params = [];
        $params['start'] = $start;
        $params['length'] = $length;
        $idVerifications = $this->repo->getOnsiteIdVerifications('PS_SITE_TEST', $params);

        $this->assertEquals($resultCount, count($idVerifications));
        $this->assertEquals($resultParticipantId, $idVerifications[0]['participantId']);

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
    public function testOnsiteIdVerificationsDateFilters($startDate, $endDate, $resultCount): void
    {
        $this->createIdVerifications();
        $params = [];
        $params['startDate'] = $this->getDate($startDate);
        $params['endDate'] = $this->getDate($endDate);
        $idVerifications = $this->repo->getOnsiteIdVerifications('PS_SITE_TEST', $params);
        $this->assertEquals($resultCount, count($idVerifications));
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
    public function testOnsiteIdVerificationsParticipantIdLookup($participantId): void
    {
        $this->createIdVerifications();
        $params = [];
        $params['participantId'] = $participantId;
        $idVerifications = $this->repo->getOnsiteIdVerifications('PS_SITE_TEST', $params);
        $this->assertEquals($participantId, $idVerifications[0]['participantId']);
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

    /**
     * @dataProvider paramsCountDataProvider
     */
    public function testGetOnsiteIdVerificationsCount($params, $resultCount): void
    {
        $this->createIdVerifications();
        $count = $this->repo->getOnsiteIdVerificationsCount('PS_SITE_TEST', $params);
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

    private function createIdVerifications(): void
    {
        $user = $this->getUser();
        foreach ($this->getIdVerificationsData() as $idVerificationData) {
            $idVerification = new IdVerification();
            $idVerification->setParticipantId($idVerificationData['participantId'])
                ->setUser($user)
                ->setSite($idVerificationData['site'])
                ->setVerifiedDate(new \DateTime($idVerificationData['verifiedDate']))
                ->setVerificationType($idVerificationData['verificationType'])
                ->setVisitType($idVerificationData['visitType'])
                ->setCreatedTs(new \DateTime($idVerificationData['createdTs']));
            $this->em->persist($idVerification);
        }
        $this->em->flush();
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

    private function getIdVerificationsData(): array
    {
        return [
            [
                'participantId' => 'P000000000',
                'site' => 'PS_SITE_TEST',
                'verifiedDate' => '2022-01-15',
                'verificationType' => 'PHOTO_AND_ONE_OF_PII',
                'visitType' => 'PMB_INITIAL_VISIT',
                'createdTs' => '2022-01-15'
            ],
            [
                'participantId' => 'P000000001',
                'site' => 'PS_SITE_TEST',
                'verifiedDate' => '2022-02-15',
                'verificationType' => 'TWO_OF_PII',
                'visitType' => 'BIOSPECIMEN_REDRAW_ONLY',
                'createdTs' => '2022-02-15'
            ],
            [
                'participantId' => 'P000000002',
                'site' => 'PS_SITE_TEST',
                'verifiedDate' => '2022-03-15',
                'verificationType' => 'PHOTO_AND_ONE_OF_PII',
                'visitType' => 'PHYSICAL_MEASUREMENTS_ONLY',
                'createdTs' => '2022-03-15'
            ],
            [
                'participantId' => 'P000000003',
                'site' => 'PS_SITE_TEST',
                'verifiedDate' => '2022-04-15',
                'verificationType' => 'TWO_OF_PII',
                'visitType' => 'BIOSPECIMEN_COLLECTION_ONLY',
                'createdTs' => '2022-04-15'
            ],
            [
                'participantId' => 'P000000004',
                'site' => 'PS_SITE_TEST',
                'verifiedDate' => '2022-05-15',
                'verificationType' => 'PHOTO_AND_ONE_OF_PII',
                'visitType' => 'RETENTION_ACTIVITIES',
                'createdTs' => '2022-05-15'
            ]
        ];
    }

    private function getDate($date): ?\DateTime
    {
        return $date ? new \DateTime($date) : null;
    }
}
