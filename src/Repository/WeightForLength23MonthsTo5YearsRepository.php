<?php

namespace App\Repository;

use App\Entity\WeightForLength23MonthsTo5Years;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WeightForLength23MonthsTo5Years>
 *
 * @method WeightForLength23MonthsTo5Years|null find($id, $lockMode = null, $lockVersion = null)
 * @method WeightForLength23MonthsTo5Years|null findOneBy(array $criteria, array $orderBy = null)
 * @method WeightForLength23MonthsTo5Years[]    findAll()
 * @method WeightForLength23MonthsTo5Years[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WeightForLength23MonthsTo5YearsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeightForLength23MonthsTo5Years::class);
    }

    public function save(WeightForLength23MonthsTo5Years $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WeightForLength23MonthsTo5Years $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
