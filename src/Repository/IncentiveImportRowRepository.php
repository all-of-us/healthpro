<?php

namespace App\Repository;

use App\Entity\IncentiveImportRow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
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

    public function getIncentiveImportRows(int $limit): array
    {
        return $this->createQueryBuilder('iir')
            ->select('iir, ii.site')
            ->innerJoin('iir.import', 'ii')
            ->where('ii.confirm = :confirm')
            ->andWhere('iir.rdrStatus = :rdrStatus')
            ->setParameters(['confirm' => 1, 'rdrStatus' => 0])
            ->orderBy('iir.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->setHint(Query::HINT_INCLUDE_META_COLUMNS, true)
            ->getResult(Query::HYDRATE_ARRAY);
    }

    public function deleteUnconfirmedImportData($date): void
    {
        $query = "DELETE iir FROM incentive_import_row iir inner join incentive_import ii on iir.import_id = ii.id where ii.created_ts < :date and ii.confirm = :confirm";
        $params = ['date' => $date, 'confirm' => 0];
        $statement = $this->getEntityManager()->getConnection()->prepare($query);
        $statement->execute($params);
    }
}
