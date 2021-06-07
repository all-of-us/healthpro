<?php

namespace App\Repository;

use App\Entity\WithdrawalLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WithdrawalLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WithdrawalLog::class);
    }

    public function getWithdrawalNotifications() :array
    {
        return $this->createQueryBuilder('w')
            ->select('count(w.id) as count, w.insertTs, w.hpoId, w.emailNotified as email')
            ->groupBy('w.hpoId, w.insertTs, w.emailNotified')
            ->orderBy('w.insertTs', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function getLatestAwardees() :array
    {
        return $this->createQueryBuilder('w')
            ->select('w.hpoId as awardeeId, max(w.withdrawalTs) as ts')
            ->groupBy('w.hpoId')
            ->getQuery()
            ->getResult()
            ;
    }
}
