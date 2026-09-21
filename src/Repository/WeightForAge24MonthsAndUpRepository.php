<?php

namespace App\Repository;

use App\Entity\WeightForAge24MonthsAndUp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WeightForAge24MonthsAndUp>
 */
class WeightForAge24MonthsAndUpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeightForAge24MonthsAndUp::class);
    }

    public function save(WeightForAge24MonthsAndUp $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WeightForAge24MonthsAndUp $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getChartsData(?string $sex = null): array
    {
        $queryBuilder = $this->createQueryBuilder('wfa');
        if ($sex) {
            $queryBuilder->where('wfa.sex = :sex')
                ->setParameter('sex', $sex);
        }
        return $queryBuilder->getQuery()->getArrayResult();
    }
}
