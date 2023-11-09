<?php

namespace App\Repository;

use App\Entity\BloodPressureDiastolicHeightPercentile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BloodPressureDiastolicHeightPercentile>
 *
 * @method BloodPressureDiastolicHeightPercentile|null find($id, $lockMode = null, $lockVersion = null)
 * @method BloodPressureDiastolicHeightPercentile|null findOneBy(array $criteria, array $orderBy = null)
 * @method BloodPressureDiastolicHeightPercentile[]    findAll()
 * @method BloodPressureDiastolicHeightPercentile[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BloodPressureDiastolicHeightPercentileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BloodPressureDiastolicHeightPercentile::class);
    }

    public function save(BloodPressureDiastolicHeightPercentile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(BloodPressureDiastolicHeightPercentile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getChartsData(): ?array
    {
        return $this->createQueryBuilder('bdhp')
            ->getQuery()
            ->getArrayResult();
    }
}
