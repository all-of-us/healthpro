<?php

namespace App\Repository;

use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Site|null find($id, $lockMode = null, $lockVersion = null)
 * @method Site|null findOneBy(array $criteria, array $orderBy = null)
 * @method Site[]    findAll()
 * @method Site[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Site::class);
    }

    /**
     * @return Site[] Returns an array of Site objects
     */

    public function getOrganizations()
    {
        return $this->createQueryBuilder('s')
            ->select('s.organization, GROUP_CONCAT(s.email) AS emails')
            ->where('s.organization IS NOT NULL')
            ->andWhere('s.status = 1')
            ->groupBy('s.organization')
            ->getQuery()
            ->getResult()
        ;
    }
}
