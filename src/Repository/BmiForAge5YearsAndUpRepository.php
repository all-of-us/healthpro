<?php

namespace App\Repository;

use App\Entity\BmiForAge5YearsAndUp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BmiForAge5YearsAndUp>
 */
class BmiForAge5YearsAndUpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BmiForAge5YearsAndUp::class);
    }

    public function save(BmiForAge5YearsAndUp $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(BmiForAge5YearsAndUp $entity, bool $flush = false): void
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
        $queryBuilder = $this->createQueryBuilder('bfa');
        if ($sex) {
            $queryBuilder->where('bfa.sex = :sex')
                ->setParameter('sex', $sex);
        }
        return $queryBuilder->getQuery()->getArrayResult();
    }
}
