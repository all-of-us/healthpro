<?php

namespace App\Repository;

use App\Entity\IncentiveImportRow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method IncentiveImportRow|null find($id, $lockMode = null, $lockVersion = null)
 * @method IncentiveImportRow|null findOneBy(array $criteria, array $orderBy = null)
 * @method IncentiveImportRow[]    findAll()
 * @method IncentiveImportRow[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IncentiveImportRowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IncentiveImportRow::class);
    }

    public function getIncentiveImportRows($limit): array
    {
        $query = "
            SELECT iir.*,
                   ii.site,
                   ii.created_ts
            FROM incentive_import_row iir
            INNER JOIN incentive_import ii ON ii.id = iir.import_id AND ii.confirm = :confirm
            WHERE iir.rdr_status = :rdrStatus
            ORDER BY iir.id ASC
        ";
        if ($limit > 0) {
            $query .= ' LIMIT ' . $limit;
        }
        return $this->getEntityManager()->getConnection()->fetchAll($query, [
            'confirm' => 1,
            'rdrStatus' => 0
        ]);
    }
}
