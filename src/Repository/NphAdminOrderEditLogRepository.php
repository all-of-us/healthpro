<?php

namespace App\Repository;

use App\Entity\NphAdminOrderEditLog;
use App\Entity\NphOrder;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NphAdminOrderEditLog>
 *
 * @method NphAdminOrderEditLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method NphAdminOrderEditLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method NphAdminOrderEditLog[]    findAll()
 * @method NphAdminOrderEditLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NphAdminOrderEditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NphAdminOrderEditLog::class);
    }

    public function getOrderEditLogs(): array
    {
        return $this->createQueryBuilder('na')
            ->select('no.site, no.biobankId, no.module, no.visitPeriod, no.timepoint, no.orderId, na.originalOrderGenerationTs, na.originalOrderGenerationTimezoneId, na.updatedOrderGenerationTs, na.updatedOrderGenerationTimezoneId, na.createdTs, na.createdTimezoneId, u.email')
            ->leftJoin(NphOrder::class, 'no', Join::WITH, 'na.orderId = no.orderId')
            ->leftJoin(User::class, 'u', Join::WITH, 'na.user = u.id')
            ->orderBy('na.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
