<?php

namespace App\Repository;

use App\Entity\DeceasedLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DeceasedLog>
 */
class DeceasedLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeceasedLog::class);
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public function getLatestOrganizations(?string $status): array
    {
        return $this->createQueryBuilder('d')
            ->select('d.organizationId, max(d.deceasedTs) as ts')
            ->where('d.deceasedStatus = :status')
            ->setParameter('status', $status)
            ->groupBy('d.organizationId')
            ->getQuery()
            ->getResult()
        ;
    }
}
