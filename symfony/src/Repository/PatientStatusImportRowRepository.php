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

    public function deleteUnconfirmedImportData($date)
    {
        $query = "DELETE psir FROM patient_status_import_rows psir inner join patient_status_import psi on psir.import_id = psi.id where psi.created_ts < :date and psi.confirm = :confirm";
        $params = ['date' => $date, 'confirm' => 0];
        $statement = $this->getEntityManager()->getConnection()->prepare($query);
        $statement->execute($params);
    }
}
