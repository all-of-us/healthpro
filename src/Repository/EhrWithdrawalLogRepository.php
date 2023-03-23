<?php

namespace App\Repository;

use App\Entity\EhrWithdrawalLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method EhrWithdrawalLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method EhrWithdrawalLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method EhrWithdrawalLog[]    findAll()
 * @method EhrWithdrawalLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EhrWithdrawalLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EhrWithdrawalLog::class);
    }

    /**
     * @return EhrWithdrawalLog[] Returns an array of EhrWithdrawalLog objects
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
