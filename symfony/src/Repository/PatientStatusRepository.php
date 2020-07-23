<?php

namespace App\Repository;

use App\Entity\PatientStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PatientStatus|null find($id, $lockMode = null, $lockVersion = null)
 * @method PatientStatus|null findOneBy(array $criteria, array $orderBy = null)
 * @method PatientStatus[]    findAll()
 * @method PatientStatus[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PatientStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PatientStatus::class);
    }
}
