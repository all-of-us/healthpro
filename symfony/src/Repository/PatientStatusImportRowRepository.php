<?php

namespace App\Repository;

use App\Entity\PatientStatusImportRow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PatientStatusImportRow|null find($id, $lockMode = null, $lockVersion = null)
 * @method PatientStatusImportRow|null findOneBy(array $criteria, array $orderBy = null)
 * @method PatientStatusImportRow[]    findAll()
 * @method PatientStatusImportRow[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PatientStatusImportRowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PatientStatusImportRow::class);
    }
}
