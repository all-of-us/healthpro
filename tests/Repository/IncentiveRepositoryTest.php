<?php

namespace App\Tests\Repository;

use App\Entity\Incentive;
use App\Entity\User;
use App\Repository\IncentiveRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IncentiveRepositoryTest extends KernelTestCase
{
    private $em;
    private $repo;

    public function setup(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repo = static::getContainer()->get(IncentiveRepository::class);

    }

    /**
     * @dataProvider paginationDataProvider
     */
    public function testOnsitePatientStatusPagination($start, $length, $resultCount, $resultParticipantId): void
    {
        $this->createIncentives();
        $params = [];
        $params['start'] = $start;
        $params['length'] = $length;
        $incentives = $this->repo->getOnSiteIncentives('PS_SITE_TEST', $params);
        $this->assertEquals($resultCount, count($incentives));
        $this->assertEquals($resultParticipantId, $incentives[0]['participantId']);

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
    public function testOnsiteIncentivesDateFilters($startDate, $endDate, $resultCount): void
    {
        $this->createIncentives();
        $params = [];
        $params['startDate'] = $this->getDate($startDate);
        $params['endDate'] = $this->getDate($endDate);
        $incentives = $this->repo->getOnSiteIncentives('PS_SITE_TEST', $params);
        $this->assertEquals($resultCount, count($incentives));

        $params = [];
        $params['startDateOfService'] = $startDate;
        $params['endDateOfService'] = $endDate;
        $incentives = $this->repo->getOnSiteIncentives('PS_SITE_TEST', $params);
        $this->assertEquals($resultCount, count($incentives));
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
    public function testOnsiteIncentivesParticipantIdLookup($participantId): void
    {
        $this->createIncentives();
        $params = [];
        $params['participantId'] = $participantId;
        $incentives = $this->repo->getOnSiteIncentives('PS_SITE_TEST', $params);
        $this->assertEquals($participantId, $incentives[0]['participantId']);
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
    public function testGetOnsiteIncentivesCount($params, $resultCount): void
    {
        $this->createIncentives();
        $count = $this->repo->getOnsiteIncentivesCount('PS_SITE_TEST', $params);
        $this->assertEquals($resultCount, $count);
    }

    public function paramsCountDataProvider()
    {
        return [
            [[], 5],
            [['participantId' => 'P000000001'], 1],
            [['startDate' => $this->getDate('2022-03-15')], 3],
            [['endDate' => $this->getDate('2022-04-15')], 4],
            [['startDateOfService' => '2022-01-15'], 5],
            [['endDateOfService' => '2022-03-15'], 3],
            [['startDate' => $this->getDate('2022-02-15'), 'endDate' => $this->getDate('2022-04-15')], 3],
            [['startDateOfService' => '2022-01-15', 'endDateOfService' => '2022-04-15'], 4]
        ];
    }

    private function createIncentives(): void
    {
        $user = $this->getUser();
        foreach ($this->getIncentivesData() as $incentiveData) {
            $incentive = new Incentive();
            $incentive->setParticipantId($incentiveData['participantId'])
                ->setUser($user)
                ->setSite($incentiveData['site'])
                ->setIncentiveDateGiven(new \DateTime($incentiveData['incentiveDateGiven']))
                ->setIncentiveType($incentiveData['incentiveType'])
                ->setOtherIncentiveType($incentiveData['otherIncentiveType'])
                ->setIncentiveOccurrence($incentiveData['incentiveOccurrence'])
                ->setOtherIncentiveOccurrence($incentiveData['otherIncentiveOccurrence'])
                ->setIncentiveAmount($incentiveData['incentiveAmount'])
                ->setGiftCardType($incentiveData['giftCardType'])
                ->setNotes($incentiveData['notes'])
                ->setCreatedTs(new \DateTime($incentiveData['createdTs']))
                ->setRecipient($incentiveData['recipient'])
                ->setDeclined($incentiveData['declined']);
            $this->em->persist($incentive);
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

    private function getIncentivesData(): array
    {
        return [
            [
                'participantId' => 'P000000000',
                'site' => 'PS_SITE_TEST',
                'incentiveDateGiven' => '2022-01-15',
                'incentiveType' => 'cash',
                'otherIncentiveType' => '',
                'incentiveOccurrence' => 'one_time',
                'otherIncentiveOccurrence' => '',
                'incentiveAmount' => '25',
                'giftCardType' => '',
                'notes' => 'test',
                'createdTs' => '2022-01-15',
                'declined' => 0,
                'recipient' => 'adult_participant'
            ],
            [
                'participantId' => 'P000000001',
                'site' => 'PS_SITE_TEST',
                'incentiveDateGiven' => '2022-02-15',
                'incentiveType' => 'voucher',
                'otherIncentiveType' => '',
                'incentiveOccurrence' => 'redraw',
                'otherIncentiveOccurrence' => '',
                'incentiveAmount' => '15',
                'giftCardType' => '',
                'notes' => 'test2',
                'createdTs' => '2022-02-15',
                'declined' => 0,
                'recipient' => 'adult_participant'
            ],
            [
                'participantId' => 'P000000002',
                'site' => 'PS_SITE_TEST',
                'incentiveDateGiven' => '2022-03-15',
                'incentiveType' => 'promotional',
                'otherIncentiveType' => '',
                'incentiveOccurrence' => 'one_time',
                'otherIncentiveOccurrence' => '',
                'incentiveAmount' => '25',
                'giftCardType' => '',
                'notes' => 'test3',
                'createdTs' => '2022-03-15',
                'declined' => 0,
                'recipient' => 'pediatric_guardian'
            ],
            [
                'participantId' => 'P000000003',
                'site' => 'PS_SITE_TEST',
                'incentiveDateGiven' => '2022-04-15',
                'incentiveType' => 'cash',
                'otherIncentiveType' => '',
                'incentiveOccurrence' => 'one_time',
                'otherIncentiveOccurrence' => '',
                'incentiveAmount' => '25',
                'giftCardType' => '',
                'notes' => 'test4',
                'createdTs' => '2022-04-15',
                'declined' => 0,
                'recipient' => 'pediatric_guardian'
            ],
            [
                'participantId' => 'P000000004',
                'site' => 'PS_SITE_TEST',
                'incentiveDateGiven' => '2022-05-15',
                'incentiveType' => 'promotional',
                'otherIncentiveType' => '',
                'incentiveOccurrence' => 'redraw',
                'otherIncentiveOccurrence' => '',
                'incentiveAmount' => '15',
                'giftCardType' => '',
                'notes' => 'test5',
                'createdTs' => '2022-05-15',
                'declined' => 0,
                'recipient' => 'pediatric_participant'
            ]
        ];
    }

    private function getDate($date): ?\DateTime
    {
        return $date ? new \DateTime($date) : null;
    }
}
