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
            ->select('s.organizationId, GROUP_CONCAT(s.email) AS emails')
            ->where('s.organizationId IS NOT NULL')
            ->andWhere('s.status = 1')
            ->groupBy('s.organizationId')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Site[] Returns an array of Site objects
     */
    public function getAwardees()
    {
        return $this->createQueryBuilder('s')
            ->select('s.awardeeId, GROUP_CONCAT(s.email) AS emails')
            ->where('s.awardeeId IS NOT NULL')
            ->andWhere('s.status = 1')
            ->groupBy('s.awardeeId')
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @return Site[] Returns an array of Site objects
     */
    public function getDuplicateSiteGoogleGroup($googleGroup, $id)
    {
        return $this->createQueryBuilder('s')
            ->select('s.id')
            ->where('s.deleted = 0')
            ->andWhere('s.googleGroup = :googleGroup')
            ->andWhere('s.id != :id')
            ->setParameters(['googleGroup' => $googleGroup, 'id' => $id])
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Site[] Returns an array of Site objects
     */
    public function getDuplicateGoogleGroup($googleGroup)
    {
        return $this->createQueryBuilder('s')
            ->select('s.id')
            ->where('s.deleted = 0')
            ->andWhere('s.googleGroup = :googleGroup')
            ->setParameter('googleGroup', $googleGroup)
            ->getQuery()
            ->getResult()
        ;
    }
}
