<?php

namespace App\Repository;

use App\Entity\NphGenerateOrderWarningLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NphGenerateOrderWarningLog>
 *
 * @method NphGenerateOrderWarningLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method NphGenerateOrderWarningLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method NphGenerateOrderWarningLog[]    findAll()
 * @method NphGenerateOrderWarningLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NphGenerateOrderWarningLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NphGenerateOrderWarningLog::class);
    }

    public function getGenerateOrderWarningLog(string $participantId, string $module, string $period): NphGenerateOrderWarningLog|null
    {
        $nphGenerateOrderWarningLog = $this->createQueryBuilder('n')
            ->andWhere('n.participantId = :participantId')
            ->andWhere('n.module = :module')
            ->andWhere('n.period = :period')
            ->setParameters(['participantId' => $participantId, 'module' => $module, 'period' => $period])
            ->getQuery()
            ->getResult()
        ;
        return !empty($nphGenerateOrderWarningLog) ? $nphGenerateOrderWarningLog[0] : null;
    }

    public function getGenerateOrderWarningLogByModule(string $participantId): ?array
    {
        $nphGenerateOrderWarningLog = $this->createQueryBuilder('n')
            ->andWhere('n.participantId = :participantId')
            ->setParameter('participantId', $participantId)
            ->getQuery()
            ->getResult()
        ;
        return !empty($nphGenerateOrderWarningLog) ? $nphGenerateOrderWarningLog : null;
    }

    public function getAuditReport(?\DateTime $startDate, ?\DateTime $endDate): array
    {
        $query = $this->createQueryBuilder('n')
            ->select('n');
        if ($startDate && $endDate) {
            $query->andWhere('n.modifiedTs >= :startDate')
                ->andWhere('n.modifiedTs <= :endDate')
                ->setParameters(['startDate' => $startDate, 'endDate' => $endDate]);
        }
        $query->orderBy('n.modifiedTs', 'DESC');
        return $query->getQuery()->getResult();
    }
}
