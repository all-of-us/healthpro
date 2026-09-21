<?php

namespace App\Repository;

use App\Entity\NphSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NphSite>
 */
class NphSiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NphSite::class);
    }

    /**
     * @return array<int, array<string, int>>
     */
    public function getDuplicateGoogleGroup(string $googleGroup, ?int $id = null): array
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
