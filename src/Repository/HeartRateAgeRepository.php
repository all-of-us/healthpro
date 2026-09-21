<?php

namespace App\Repository;

use App\Entity\HeartRateAge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HeartRateAge>
 */
class HeartRateAgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HeartRateAge::class);
    }

    public function save(HeartRateAge $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(HeartRateAge $entity, bool $flush = false): void
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
        return $this->createQueryBuilder('hra')
            ->getQuery()
            ->getArrayResult();
    }
}
