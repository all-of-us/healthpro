<?php

namespace App\Repository;

use App\Entity\BloodPressureSystolicHeightPercentile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BloodPressureSystolicHeightPercentile>
 */
class BloodPressureSystolicHeightPercentileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BloodPressureSystolicHeightPercentile::class);
    }

    public function save(BloodPressureSystolicHeightPercentile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(BloodPressureSystolicHeightPercentile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getChartsData(): array
    {
        return $this->createQueryBuilder('bshp')
            ->getQuery()
            ->getArrayResult();
    }
}
