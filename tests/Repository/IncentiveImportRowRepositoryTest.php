<?php

namespace App\Tests\Repository;

use App\Entity\IncentiveImport;
use App\Entity\IncentiveImportRow;
use App\Entity\User;
use App\Repository\IncentiveImportRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IncentiveImportRowRepositoryTest extends KernelTestCase
{
    private $em;
    private $repo;

    public function setup(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repo = static::getContainer()->get(IncentiveImportRowRepository::class);

    }

    public function testGetIncentiveImportRows(): void
    {
        $incentiveImport = $this->createIncentiveImport(1);
        $this->createIncentiveImportRows($incentiveImport);
        $incentiveImportRows = $this->repo->getIncentiveImportRows(10);
        foreach ($incentiveImportRows as $incentiveImportRow) {
            if ($incentiveImportRow[0]['participantId'] === 'P000000001') {
                $this->assertEquals($incentiveImportRow[0]['incentiveType'], 'cash');
                $this->assertEquals($incentiveImportRow['site'], 'test-site');
            }
            if ($incentiveImportRow[0]['participantId'] === 'P000000002') {
                $this->assertEquals($incentiveImportRow[0]['incentiveType'], 'promotional');
                $this->assertEquals($incentiveImportRow['site'], 'test-site');
            }
        }
    }

    public function testDeleteUnconfirmedImportData(): void
    {
        $incentiveImport = $this->createIncentiveImport();
        $this->createIncentiveImportRows($incentiveImport);
        // Before delete
        $incentiveImportRows = $this->repo->findBy(
            ['participantId' => ['P000000001', 'P000000002']]
        );
        $this->assertEquals(count($incentiveImportRows), 2);
        $this->repo->deleteUnconfirmedImportData('2022-06-06 17:37:23');
        // After delete
        $incentiveImportRows = $this->repo->findBy(
            ['participantId' => ['P000000001', 'P000000002']]
        );
        $this->assertEquals(count($incentiveImportRows), 0);
    }

    private function createIncentiveImport($confirm = 0): IncentiveImport
    {
        $incentiveImport = new IncentiveImport();
        $incentiveImport->setUser($this->getUser())
            ->setFileName('Test.csv')
            ->setSite('test-site')
            ->setCreatedTs(new \DateTime('06/04/2022'))
            ->setImportStatus(0)
            ->setConfirm($confirm);
        $this->em->persist($incentiveImport);
        $this->em->flush();
        return $incentiveImport;
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


    private function createIncentiveImportRows($incentiveImport): void
    {
        foreach ($this->getIncentiveImportData() as $importData) {
            $incentiveImportRow = new IncentiveImportRow();
            $incentiveImportRow->setImport($incentiveImport)
                ->setIncentiveDateGiven($importData['incentiveDateGiven'])
                ->setIncentiveType($importData['incentiveType'])
                ->setOtherIncentiveType($importData['otherIncentiveType'])
                ->setIncentiveAmount($importData['incentiveAmount'])
                ->setGiftCardType($importData['giftCardType'])
                ->setNotes($importData['notes'])
                ->setDeclined($importData['declined'])
                ->setParticipantId($importData['participantId'])
                ->setUserEmail($importData['userEmail']);
            $this->em->persist($incentiveImportRow);
        }
        $this->em->flush();
    }

    private function getIncentiveImportData(): array
    {
        return [
            [
                'incentiveDateGiven' => new \Datetime('06/04/2022'),
                'incentiveType' => 'cash',
                'otherIncentiveType' => '',
                'incentiveOccurrence' => 'one_time',
                'otherIncentiveOccurrence' => '',
                'incentiveAmount' => 15,
                'giftCardType' => '',
                'notes' => '',
                'declined' => 0,
                'participantId' => 'P000000001',
                'userEmail' => 'test1@example.com'
            ],
            [
                'incentiveDateGiven' => new \Datetime('06/04/2022'),
                'incentiveType' => 'promotional',
                'otherIncentiveType' => '',
                'incentiveOccurrence' => 'redraw',
                'otherIncentiveOccurrence' => '',
                'incentiveAmount' => 0,
                'giftCardType' => '',
                'notes' => 'Test notes',
                'declined' => 1,
                'participantId' => 'P000000002',
                'userEmail' => 'test2@example.com'
            ]
        ];
    }
}
