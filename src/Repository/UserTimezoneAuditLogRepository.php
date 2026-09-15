<?php

namespace App\Repository;

use App\Entity\UserTimezoneAuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserTimezoneAuditLog>
 */
class UserTimezoneAuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserTimezoneAuditLog::class);
    }
}
