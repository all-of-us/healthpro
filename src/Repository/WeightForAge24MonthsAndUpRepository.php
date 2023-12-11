<?php

namespace App\Repository;

use App\Entity\WeightForAge24MonthsAndUp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WeightForAge24MonthsAndUp>
 *
 * @method WeightForAge24MonthsAndUp|null find($id, $lockMode = null, $lockVersion = null)
 * @method WeightForAge24MonthsAndUp|null findOneBy(array $criteria, array $orderBy = null)
 * @method WeightForAge24MonthsAndUp[]    findAll()
 * @method WeightForAge24MonthsAndUp[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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

    public function getChartsData($sex): ?array
    {
        $queryBuilder = $this->createQueryBuilder('wfa');
        if ($sex) {
            $queryBuilder->where('wfa.sex = :sex')
                ->setParameter('sex', $sex);
        }
        return $queryBuilder->getQuery()->getArrayResult();
    }
}
