<?php

namespace App\Repository;

use App\Entity\EhrWithdrawalLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EhrWithdrawalLog>
 */
class EhrWithdrawalLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EhrWithdrawalLog::class);
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public function getLatestAwardees()
    {
        return $this->createQueryBuilder('e')
            ->select('e.awardeeId, max(e.ehrWithdrawalTs) as ts')
            ->groupBy('e.awardeeId')
            ->getQuery()
            ->getResult()
        ;
    }
}
