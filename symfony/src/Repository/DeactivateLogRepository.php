<?php

namespace App\Repository;

use App\Entity\DeactivateLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DeactivateLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method DeactivateLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method DeactivateLog[]    findAll()
 * @method DeactivateLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeactivateLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeactivateLog::class);
    }

    /**
     * @return DeactivateLog[] Returns an array of DeactivateLog objects
     */
    public function getDeactivatedNotifications()
    {
        return $this->createQueryBuilder('d')
            ->select('count(d.id) as count, d.insertTs, d.hpoId, d.emailNotified as email')
            ->groupBy('d.hpoId, d.insertTs, d.emailNotified')
            ->orderBy('d.insertTs', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return DeactivateLog[] Returns an array of DeactivateLog objects
     */
    public function getLatestAwardees()
    {
        return $this->createQueryBuilder('d')
            ->select('d.hpoId as awardeeId, max(d.deactivateTs) as ts')
            ->groupBy('d.hpoId')
            ->getQuery()
            ->getResult()
            ;
    }
}
