<?php

namespace App\Repository;

use App\Entity\MeasurementHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MeasurementHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method MeasurementHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method MeasurementHistory[]    findAll()
 * @method MeasurementHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MeasurementHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MeasurementHistory::class);
    }
}
