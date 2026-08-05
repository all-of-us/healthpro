<?php

namespace App\Repository;

use App\Entity\HeadCircumferenceForAge0To36Months;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HeadCircumferenceForAge0To36Months>
 */
class HeadCircumferenceForAge0To36MonthsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HeadCircumferenceForAge0To36Months::class);
    }

    public function save(HeadCircumferenceForAge0To36Months $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(HeadCircumferenceForAge0To36Months $entity, bool $flush = false): void
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
        $queryBuilder = $this->createQueryBuilder('hca');
        if ($sex) {
            $queryBuilder->where('hca.sex = :sex')
                ->setParameter('sex', $sex);
        }
        return $queryBuilder->getQuery()->getArrayResult();
    }
}
