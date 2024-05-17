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
        $moduleAndTimestamps = $this->createQueryBuilder('n')
            ->select('n.module, max(n.modifiedTs) as timestamp, n.period')
            ->andWhere('n.participantId = :participantId')
            ->setParameter('participantId', $participantId)
            ->groupBy('n.module, n.period')
            ->getQuery()
            ->getResult();
        $modules = [];
        $timestamps = [];
        $periods = [];
        foreach ($moduleAndTimestamps as $moduleAndTimestamp) {
            $modules[] = $moduleAndTimestamp['module'];
            $timestamps[] = $moduleAndTimestamp['timestamp'];
            $periods[] = $moduleAndTimestamp['period'];
        }
        $nphSampleProcessingStatus = $this->createQueryBuilder('n')
            ->andWhere('n.participantId = :participantId')
            ->andWhere('n.module IN (:modules)')
            ->andWhere('n.modifiedTs IN (:timestamps)')
            ->andWhere('n.period IN (:periods)')
            ->setParameter('participantId', $participantId)
            ->setParameter('modules', $modules)
            ->setParameter('timestamps', $timestamps)
            ->setParameter('periods', $periods)
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
            ->orderBy(n.modifiedTs, 'DESC')
            ->setMaxResults(1)
            ->setParameters(['participantId' => $participantId, 'module' => $module, 'period' => $period])
            ->getQuery()
            ->getResult()
        ;
        return !empty($nphSampleProcessingStatus);
    }
}
