<?php

namespace App\Repository;

use App\Entity\NphSampleProcessingStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NphSampleProcessingStatus>
 *
 * @method NphSampleProcessingStatus|null find($id, $lockMode = null, $lockVersion = null)
 * @method NphSampleProcessingStatus|null findOneBy(array $criteria, array $orderBy = null)
 * @method NphSampleProcessingStatus[]    findAll()
 * @method NphSampleProcessingStatus[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NphSampleProcessingStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NphSampleProcessingStatus::class);
    }

    public function getSampleProcessingStatus(string $participantId, string $module, string $period): NphSampleProcessingStatus|null
    {
        $nphSampleProcessingStatus = $this->createQueryBuilder('n')
            ->andWhere('n.participantId = :participantId')
            ->andWhere('n.module = :module')
            ->andWhere('n.period = :period')
            ->setParameters(['participantId' => $participantId, 'module' => $module, 'period' => $period])
            ->getQuery()
            ->getResult()
        ;
        return !empty($nphSampleProcessingStatus) ? $nphSampleProcessingStatus[0] : null;
    }

    public function getSampleProcessingStatusByModule(string $participantId): ?array
    {
        $nphSampleProcessingStatus = $this->createQueryBuilder('n')
            ->andWhere('n.participantId = :participantId')
            ->setParameter('participantId', $participantId)
            ->getQuery()
            ->getResult();
        return !empty($nphSampleProcessingStatus) ? $nphSampleProcessingStatus : null;
    }

    public function isSampleProcessingComplete(string $participantId, string $module, string $period): bool
    {
        $nphSampleProcessingStatus = $this->createQueryBuilder('n')
            ->andWhere('n.participantId = :participantId')
            ->andWhere('n.module = :module')
            ->andWhere('n.period = :period')
            ->andWhere('n.status = 1')
            ->setMaxResults(1)
            ->setParameters(['participantId' => $participantId, 'module' => $module, 'period' => $period])
            ->getQuery()
            ->getResult()
        ;
        return !empty($nphSampleProcessingStatus);
    }

    public function getAuditReport(?\DateTime $startDate, ?\DateTime $endDate): array
    {
        $nphSampleProcessingStatus = $this->createQueryBuilder('n');
        if ($startDate && $endDate) {
            $nphSampleProcessingStatus->andWhere('n.modifiedTs >= :startDate')
                ->andWhere('n.modifiedTs <= :endDate')
                ->setParameters(['startDate' => $startDate, 'endDate' => $endDate]);
        }
        $nphSampleProcessingStatus->orderBy('n.modifiedTs', 'DESC');
        $nphSampleProcessingStatus = $nphSampleProcessingStatus->getQuery()->getResult();
        return !empty($nphSampleProcessingStatus) ? $nphSampleProcessingStatus : [];
    }
}
