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
        $query = 'DELETE psir FROM patient_status_import_rows psir inner join patient_status_import psi on psir.import_id = psi.id where psi.created_ts < :date and psi.confirm = :confirm';
        $params = ['date' => $date, 'confirm' => 0];
        $statement = $this->getEntityManager()->getConnection()->prepare($query);
        $statement->execute($params);
    }

    public function getPatientStatusImportRows($limit): array
    {
        $query = '
            SELECT psir.*,
                   psi.site,
                   psi.created_ts as authored,
                   psi.organization,
                   psi.awardee,
                   ps.id as patient_status_id,
                   u.id as user_id,
                   u.email as user_email
            FROM patient_status_import_rows psir
            INNER JOIN patient_status_import psi ON psi.id = psir.import_id AND psi.confirm = :confirm
            LEFT JOIN patient_status ps ON ps.participant_id = psir.participant_id AND psi.organization = ps.organization
            LEFT JOIN users u ON psi.user_id = u.id
            WHERE psir.rdr_status = :rdrStatus
            ORDER BY psir.id ASC
        ';
        if ($limit > 0) {
            $query .= ' LIMIT ' . $limit;
        }
        return $this->getEntityManager()->getConnection()->fetchAllAssociative($query, [
            'confirm' => 1,
            'rdrStatus' => 0
        ]);
    }
}
