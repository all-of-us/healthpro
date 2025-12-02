<?php

namespace App\Repository;

use App\Entity\HeightForAge24MonthsAndUp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HeightForAge24MonthsAndUp>
 *
 * @method HeightForAge24MonthsAndUp|null find($id, $lockMode = null, $lockVersion = null)
 * @method HeightForAge24MonthsAndUp|null findOneBy(array $criteria, array $orderBy = null)
 * @method HeightForAge24MonthsAndUp[]    findAll()
 * @method HeightForAge24MonthsAndUp[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HeightForAge24MonthsAndUpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HeightForAge24MonthsAndUp::class);
    }

    public function save(HeightForAge24MonthsAndUp $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(HeightForAge24MonthsAndUp $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getChartsData($sex): ?array
    {
        $queryBuilder = $this->createQueryBuilder('hfa');
        if ($sex) {
            $queryBuilder->where('hfa.sex = :sex')
                ->setParameter('sex', $sex);
        }
        return $queryBuilder->getQuery()->getArrayResult();
    }
}
