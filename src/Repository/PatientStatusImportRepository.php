<?php

namespace App\Repository;

use App\Entity\PatientStatusImport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PatientStatusImport|null find($id, $lockMode = null, $lockVersion = null)
 * @method PatientStatusImport|null findOneBy(array $criteria, array $orderBy = null)
 * @method PatientStatusImport[]    findAll()
 * @method PatientStatusImport[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PatientStatusImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PatientStatusImport::class);
    }
}
