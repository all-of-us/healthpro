<?php

namespace App\Repository;

use App\Entity\WeightForLength0To23Months;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WeightForLength0To23Months>
 *
 * @method WeightForLength0To23Months|null find($id, $lockMode = null, $lockVersion = null)
 * @method WeightForLength0To23Months|null findOneBy(array $criteria, array $orderBy = null)
 * @method WeightForLength0To23Months[]    findAll()
 * @method WeightForLength0To23Months[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WeightForLength0To23MonthsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeightForLength0To23Months::class);
    }

    public function save(WeightForLength0To23Months $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WeightForLength0To23Months $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }


    public function getChartsData($sex): ?array
    {
        $queryBuilder = $this->createQueryBuilder('wfl');
        if ($sex) {
            $queryBuilder->where('wfl.sex = :sex')
                ->setParameter('sex', $sex);
        }
        return $queryBuilder->getQuery()->getArrayResult();
    }
}
