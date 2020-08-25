<?php

namespace App\Repository;

use App\Entity\WithdrawalLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method WithdrawalLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method WithdrawalLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method WithdrawalLog[]    findAll()
 * @method WithdrawalLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WithdrawalLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WithdrawalLog::class);
    }

    /**
     * @return WithdrawalLog[] Returns an array of WithdrawalLog objects
     */

    public function getWithdrawalLogs()
    {
        return $this->createQueryBuilder('w')
            ->select('count(w.id) as count, w.insertTs, w.hpoId, w.emailNotified as email')
            ->groupBy('w.hpoId')
            ->addGroupBy('w.insertTs')
            ->addGroupBy('w.emailNotified')
            ->orderBy('w.insertTs', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
