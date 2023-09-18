<?php

namespace App\Repository;

use App\Entity\BloodPressureSystolicHeightPercentile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BloodPressureSystolicHeightPercentile>
 *
 * @method BloodPressureSystolicHeightPercentile|null find($id, $lockMode = null, $lockVersion = null)
 * @method BloodPressureSystolicHeightPercentile|null findOneBy(array $criteria, array $orderBy = null)
 * @method BloodPressureSystolicHeightPercentile[]    findAll()
 * @method BloodPressureSystolicHeightPercentile[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BloodPressureSystolicHeightPercentileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BloodPressureSystolicHeightPercentile::class);
    }

    public function save(BloodPressureSystolicHeightPercentile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(BloodPressureSystolicHeightPercentile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
