<?php

namespace App\Repository;

use App\Entity\SiteSync;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SiteSync|null find($id, $lockMode = null, $lockVersion = null)
 * @method SiteSync|null findOneBy(array $criteria, array $orderBy = null)
 * @method SiteSync[]    findAll()
 * @method SiteSync[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SiteSyncRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteSync::class);
    }
}
