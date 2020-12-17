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
}
