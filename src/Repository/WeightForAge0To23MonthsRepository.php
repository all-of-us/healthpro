<?php

namespace App\Repository;

use App\Entity\WeightForAge0To23Months;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WeightForAge0To23Months>
 *
 * @method WeightForAge0To23Months|null find($id, $lockMode = null, $lockVersion = null)
 * @method WeightForAge0To23Months|null findOneBy(array $criteria, array $orderBy = null)
 * @method WeightForAge0To23Months[]    findAll()
 * @method WeightForAge0To23Months[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WeightForAge0To23MonthsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeightForAge0To23Months::class);
    }

    public function save(WeightForAge0To23Months $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WeightForAge0To23Months $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
