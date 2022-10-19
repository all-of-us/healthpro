<?php

namespace App\Repository;

use App\Entity\NphSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NphSite|null find($id, $lockMode = null, $lockVersion = null)
 * @method NphSite|null findOneBy(array $criteria, array $orderBy = null)
 * @method NphSite[]    findAll()
 * @method NphSite[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NphSiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NphSite::class);
    }

    /**
     * @return NphSite[] Returns an array of NphSite objects
     */
    public function getDuplicateGoogleGroup($googleGroup, $id = null)
    {
        $queryBuilder = $this->createQueryBuilder('ns')
            ->select('ns.id');

        $queryBuilder
            ->where('ns.deleted = 0')
            ->andWhere('ns.googleGroup = :googleGroup')
            ->setParameter('googleGroup', $googleGroup);

        if ($id) {
            $queryBuilder
                ->andWhere('ns.id != :id')
                ->setParameter('id', $id);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
