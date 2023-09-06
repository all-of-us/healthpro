<?php

namespace App\Repository;

use App\Entity\HeightForAge24MonthsTo6Years;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HeightForAge24MonthsTo6Years>
 *
 * @method HeightForAge24MonthsTo6Years|null find($id, $lockMode = null, $lockVersion = null)
 * @method HeightForAge24MonthsTo6Years|null findOneBy(array $criteria, array $orderBy = null)
 * @method HeightForAge24MonthsTo6Years[]    findAll()
 * @method HeightForAge24MonthsTo6Years[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HeightForAge24MonthsTo6YearsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HeightForAge24MonthsTo6Years::class);
    }

    public function save(HeightForAge24MonthsTo6Years $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(HeightForAge24MonthsTo6Years $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
