<?php

namespace App\Repository;

use App\Entity\HeightForAge0To23Months;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HeightForAge0To23Months>
 *
 * @method HeightForAge0To23Months|null find($id, $lockMode = null, $lockVersion = null)
 * @method HeightForAge0To23Months|null findOneBy(array $criteria, array $orderBy = null)
 * @method HeightForAge0To23Months[]    findAll()
 * @method HeightForAge0To23Months[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HeightForAge0To23MonthsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HeightForAge0To23Months::class);
    }

    public function save(HeightForAge0To23Months $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(HeightForAge0To23Months $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
