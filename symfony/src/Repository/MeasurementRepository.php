<?php

namespace App\Repository;

use App\Entity\Measurement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Measurement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Measurement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Measurement[]    findAll()
 * @method Measurement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MeasurementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Measurement::class);
    }

     /**
      * @return Measurement[] Returns an array of Measurement objects
      */
    public function getMissingMeasurements()
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.history', 'mh')
            ->where('m.finalizedTs is not null')
            ->andWhere('m.rdrId is null')
            ->andWhere('mh.type != :type OR mh.type is null')
            ->setParameter('type', 'cancel')
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
