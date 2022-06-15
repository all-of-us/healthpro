<?php

namespace App\Repository;

use App\Entity\IdVerificationImportRow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method IdVerificationImportRow|null find($id, $lockMode = null, $lockVersion = null)
 * @method IdVerificationImportRow|null findOneBy(array $criteria, array $orderBy = null)
 * @method IdVerificationImportRow[]    findAll()
 * @method IdVerificationImportRow[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IdVerificationImportRowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IdVerificationImportRow::class);
    }

    public function getIdVerificationImportRows(int $limit): array
    {
        return $this->createQueryBuilder('ivir')
            ->select('ivir, ivi.site')
            ->innerJoin('ivir.import', 'ivi')
            ->where('ivi.confirm = :confirm')
            ->andWhere('ivir.rdrStatus = :rdrStatus')
            ->setParameters(['confirm' => 1, 'rdrStatus' => 0])
            ->orderBy('ivir.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->setHint(Query::HINT_INCLUDE_META_COLUMNS, true)
            ->getResult(Query::HYDRATE_ARRAY);
    }

    public function deleteUnconfirmedImportData($date): void
    {
        $query = "DELETE ivir FROM id_verification_import_row ivir inner join id_verification_import ivi on ivir.import_id = ivi.id where ivi.created_ts < :date and ivi.confirm = :confirm";
        $params = ['date' => $date, 'confirm' => 0];
        $statement = $this->getEntityManager()->getConnection()->prepare($query);
        $statement->execute($params);
    }
}
