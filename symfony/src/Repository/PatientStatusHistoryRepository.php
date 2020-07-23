<?php

namespace App\Repository;

use App\Entity\PatientStatusHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PatientStatusHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method PatientStatusHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method PatientStatusHistory[]    findAll()
 * @method PatientStatusHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PatientStatusHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PatientStatusHistory::class);
    }
}
