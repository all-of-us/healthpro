<?php

namespace App\Repository;

use App\Entity\Incentive;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Incentive|null find($id, $lockMode = null, $lockVersion = null)
 * @method Incentive|null findOneBy(array $criteria, array $orderBy = null)
 * @method Incentive[]    findAll()
 * @method Incentive[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IncentiveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Incentive::class);
    }

    public function search(string $query): array
    {
        $query = trim($query);
        $queryParts = preg_split('/\W+/', $query, 20, PREG_SPLIT_NO_EMPTY);
        if (count($queryParts) === 0) {
            return [];
        }
        $queryBuilder = $this->createQueryBuilder('i')
            ->select('i.giftCardType')
            ->groupBy('i.giftCardType')
            ->orderBy('count(i.giftCardType)', 'DESC')
            ->setMaxResults(10);

        foreach ($queryParts as $i => $queryPart) {
            $parameter = "%{$queryPart}%";
            $queryBuilder
                ->andWhere("i.giftCardType like ?{$i}")
                ->setParameter($i, $parameter);
        }
        return $queryBuilder->getQuery()->getResult();
    }

    public function getOnsiteIncentives($site, $params): array
    {
        $queryBuilder = $this->createQueryBuilder('i')
            ->select('i.createdTs, i.participantId, i.site, i.incentiveDateGiven, i.incentiveOccurrence,
                i.otherIncentiveOccurrence, i.incentiveType, i.otherIncentiveType, i.incentiveAmount, i.giftCardType,
                i.declined, i.notes, u.email, ii.id as importId')
            ->leftJoin('i.user', 'u')
            ->leftJoin('i.import', 'ii')
            ->where('i.site =:site');

        if (!empty($params['participantId'])) {
            $queryBuilder->andWhere('i.participantId = :participantId')
                ->setParameter('participantId', $params['participantId']);
        }

        if (!empty($params['startDate'])) {
            $queryBuilder->andWhere('i.createdTs >= :startDate')
                ->setParameter('startDate', $params['startDate']);
        }

        if (!empty($params['endDate'])) {
            $queryBuilder->andWhere('i.createdTs <= :endDate')
                ->setParameter('endDate', $params['endDate']);
        }

        $queryBuilder->setParameter('site', $site);

        if (isset($params['sortColumn'])) {
            $queryBuilder->orderBy($params['sortColumn'], $params['sortDir']);
        } else {
            $queryBuilder->orderBy('i.id', 'ASC');
        }

        if (isset($params['start'])) {
            return $queryBuilder
                ->getQuery()
                ->setFirstResult($params['start'])
                ->setMaxResults($params['length'])
                ->getResult();
        } else {
            return $queryBuilder
                ->getQuery()
                ->getResult();
        }
    }

    public function getOnsiteIncentivesCount($site, $params): int
    {
        $queryBuilder = $this->createQueryBuilder('i')
            ->select('count(i.id)')
            ->leftJoin('i.user', 'u')
            ->leftJoin('i.import', 'ii')
            ->where('i.site =:site');
        if (!empty($params['participantId'])) {
            $queryBuilder->andWhere('i.participantId = :participantId')
                ->setParameter('participantId', $params['participantId']);
        }

        if (!empty($params['startDate'])) {
            $queryBuilder->andWhere('i.createdTs >= :startDate')
                ->setParameter('startDate', $params['startDate']);
        }

        if (!empty($params['endDate'])) {
            $queryBuilder->andWhere('i.createdTs <= :endDate')
                ->setParameter('endDate', $params['endDate']);
        }
        $queryBuilder->setParameter('site', $site);

        return $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }
}
