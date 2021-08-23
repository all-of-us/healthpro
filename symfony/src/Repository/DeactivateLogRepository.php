<?php

namespace App\Repository;

use App\Entity\DeactivateLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DeactivateLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeactivateLog::class);
    }

    public function getDeactivatedNotifications(): array
    {
        return $this->createQueryBuilder('d')
            ->select('count(d.id) as count, d.insertTs, d.hpoId, d.emailNotified as email')
            ->groupBy('d.hpoId, d.insertTs, d.emailNotified')
            ->orderBy('d.insertTs', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function getLatestAwardees(): array
    {
        return $this->createQueryBuilder('d')
            ->select('d.hpoId as awardeeId, max(d.deactivateTs) as ts')
            ->groupBy('d.hpoId')
            ->getQuery()
            ->getResult()
            ;
    }
}
