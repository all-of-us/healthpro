<?php

namespace App\Tests\Repository;

use App\Entity\IdVerificationImport;
use App\Entity\IdVerificationImportRow;
use App\Entity\User;
use App\Repository\IdVerificationImportRowRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IdVerificationImportRowRepositoryTest extends KernelTestCase
{
    private $em;
    private $repo;

    public function setup(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repo = static::getContainer()->get(IdVerificationImportRowRepository::class);

    }

    public function testGetIdVerificationImportRows(): void
    {
        $idVerificationImport = $this->createIdVerificationImport(1);
        $this->createIdVerificationImportRows($idVerificationImport);
        $idVerificationImportRows = $this->repo->getIdVerificationImportRows(10);
        foreach ($idVerificationImportRows as $idVerificationImportRow) {
            if ($idVerificationImportRow[0]['participantId'] === 'P000000001') {
                $this->assertEquals($idVerificationImportRow[0]['verificationType'], 'PHOTO_AND_ONE_OF_PII');
                $this->assertEquals($idVerificationImportRow[0]['visitType'], 'PMB_INITIAL_VISIT');
                $this->assertEquals($idVerificationImportRow['site'], 'test-site');
            }
            if ($idVerificationImportRow[0]['participantId'] === 'P000000002') {
                $this->assertEquals($idVerificationImportRow[0]['verificationType'], 'TWO_OF_PII');
                $this->assertEquals($idVerificationImportRow[0]['visitType'], 'BIOSPECIMEN_REDRAW_ONLY');
                $this->assertEquals($idVerificationImportRow['site'], 'test-site');
            }
        }
    }

    public function testDeleteUnconfirmedImportData(): void
    {
        $idVerificationImport = $this->createIdVerificationImport();
        $this->createIdVerificationImportRows($idVerificationImport);
        // Before delete
        $idVerificationImportRows = $this->repo->findBy(
            ['participantId' => ['P000000001', 'P000000002']]
        );
        $this->assertEquals(count($idVerificationImportRows), 2);
        $this->repo->deleteUnconfirmedImportData('2022-06-06 17:37:23');
        // After delete
        $idVerificationImportRows = $this->repo->findBy(
            ['participantId' => ['P000000001', 'P000000002']]
        );
        $this->assertEquals(count($idVerificationImportRows), 0);
    }

    private function createIdVerificationImport($confirm = 0): IdVerificationImport
    {
        $idVerificationImport = new IdVerificationImport();
        $idVerificationImport->setUser($this->getUser())
            ->setFileName('Test.csv')
            ->setSite('test-site')
            ->setCreatedTs(new \DateTime('06/04/2022'))
            ->setImportStatus(0)
            ->setConfirm($confirm);
        $this->em->persist($idVerificationImport);
        $this->em->flush();
        return $idVerificationImport;
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


    private function createIdVerificationImportRows($idVerificationImport): void
    {
        foreach ($this->getIdVerificationImportData() as $importData) {
            $idVerificationImportRow = new IdVerificationImportRow();
            $idVerificationImportRow->setImport($idVerificationImport)
                ->setParticipantId($importData['participantId'])
                ->setUserEmail($importData['userEmail'])
                ->setVerifiedDate($importData['verifiedDate'])
                ->setVerificationType($importData['verificationType'])
                ->setVisitType($importData['visitType']);
            $this->em->persist($idVerificationImportRow);
        }
        $this->em->flush();
    }

    private function getIdVerificationImportData(): array
    {
        return [
            [
                'participantId' => 'P000000001',
                'userEmail' => 'test1@example.com',
                'verifiedDate' => new \Datetime('06/13/2022'),
                'verificationType' => 'PHOTO_AND_ONE_OF_PII',
                'visitType' => 'PMB_INITIAL_VISIT'
            ],
            [
                'participantId' => 'P000000002',
                'userEmail' => 'test2@example.com',
                'verifiedDate' => new \Datetime('06/14/2022'),
                'verificationType' => 'TWO_OF_PII',
                'visitType' => 'BIOSPECIMEN_REDRAW_ONLY'
            ]
        ];
    }
}
