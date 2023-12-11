<?php

namespace App\Repository;

use App\Entity\BmiForAge5YearsAndUp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BmiForAge5YearsAndUp>
 *
 * @method BmiForAge5YearsAndUp|null find($id, $lockMode = null, $lockVersion = null)
 * @method BmiForAge5YearsAndUp|null findOneBy(array $criteria, array $orderBy = null)
 * @method BmiForAge5YearsAndUp[]    findAll()
 * @method BmiForAge5YearsAndUp[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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

    public function getChartsData($sex): ?array
    {
        $queryBuilder = $this->createQueryBuilder('bfa');
        if ($sex) {
            $queryBuilder->where('bfa.sex = :sex')
                ->setParameter('sex', $sex);
        }
        return $queryBuilder->getQuery()->getArrayResult();
    }
}
