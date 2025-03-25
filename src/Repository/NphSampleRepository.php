<?php

namespace App\Repository;

use App\Entity\NphOrder;
use App\Entity\NphSample;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NphSample|null find($id, $lockMode = null, $lockVersion = null)
 * @method NphSample|null findOneBy(array $criteria, array $orderBy = null)
 * @method NphSample[]    findAll()
 * @method NphSample[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NphSampleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NphSample::class);
    }

    public function findActiveSampleCodes(NphOrder $order, $site)
    {
        return $this->createQueryBuilder('s')
            ->select('s.sampleCode')
            ->join('s.nphOrder', 'o')
            ->andWhere('o.participantId = :participantId')
            ->andWhere('o.module = :module')
            ->andWhere('o.timepoint = :timepoint')
            ->andWhere('o.visitPeriod = :visitPeriod')
            ->andWhere('o.site = :site')
            ->andWhere('s.modifyType IN (:types) or s.modifyType is null')
            ->setParameters([
                'participantId' => $order->getParticipantId(),
                'module' => $order->getModule(),
                'timepoint' => $order->getTimepoint(),
                'visitPeriod' => $order->getVisitPeriod(),
                'site' => $site,
                'types' => [NphSample::RESTORE, NphSample::UNLOCK, NphSample::EDITED, NphSample::REVERT]
            ])
            ->getQuery()
            ->getResult(\Doctrine\ORM\Query::HYDRATE_SCALAR_COLUMN)
        ;
    }

    public function findActiveSamplesByParticipantId(string $participantId, string $module): array
    {
        $qb = $this->createQueryBuilder('s');
        $qb
            ->join('s.nphOrder', 'o')
            ->where('o.participantId = :participantId')
            ->andWhere('o.module = :module')
            ->andWhere('s.modifyType IN (:types) OR s.modifyType IS NULL')
            ->setParameters([
                'participantId' => $participantId,
                'module' => $module,
                'types' => [NphSample::RESTORE, NphSample::UNLOCK, NphSample::EDITED, NphSample::REVERT]
            ]);

        $connection = $this->getEntityManager()->getConnection();

        // Define sort orders
        $sortOrders = [
            'o.timepoint' => [
                'preLMT', 'preDSMT', 'minus15min', 'minus5min', '15min', '30min',
                '60min', '90min', '120min', '180min', '240min', 'postLMT', 'postDSMT', 'day0'
            ],
            'o.orderType' => ['urine', 'saliva', 'hair', 'nail', 'stool', 'blood'],
            's.sampleCode' => ['ST1', 'ST2', 'ST3', 'ST4', 'SST8P5', 'LIHP1', 'EDTA10', 'P800']
        ];

        // Generate case expressions for sorting
        foreach ($sortOrders as $field => $orderList) {
            $qb->addOrderBy($this->buildCaseExpression($connection, $field, $orderList), 'ASC');
        }

        return $qb->getQuery()->getResult();
    }

    private function buildCaseExpression($connection, string $field, array $orderList): string
    {
        $cases = [];
        foreach ($orderList as $index => $value) {
            $quotedValue = $connection->quote($value);
            $cases[] = "WHEN $field = $quotedValue THEN $index";
        }
        return 'CASE ' . implode(' ', $cases) . ' ELSE 999 END';
    }
}
