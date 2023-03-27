<?php

namespace App\Repository;

use App\Entity\DeceasedLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DeceasedLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method DeceasedLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method DeceasedLog[]    findAll()
 * @method DeceasedLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeceasedLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeceasedLog::class);
    }

    /**
     * @return DeceasedLog[] Returns an array of DeceasedLog objects
     */
    public function getLatestOrganizations($status)
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
