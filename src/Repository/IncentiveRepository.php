<?php

namespace App\Repository;

use App\Entity\Incentive;
use App\Helper\Participant;
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

    public function search(string $query, string $field = 'i.giftCardType'): array
    {
        $query = trim($query);
        $queryParts = preg_split('/\W+/', $query, 20, PREG_SPLIT_NO_EMPTY);
        if (count($queryParts) === 0) {
            return [];
        }
        $queryBuilder = $this->createQueryBuilder('i')
            ->select($field)
            ->groupBy($field)
            ->orderBy("count($field)", 'DESC')
            ->setMaxResults(10);

        foreach ($queryParts as $i => $queryPart) {
            $parameter = "%{$queryPart}%";
            $queryBuilder
                ->andWhere("$field like ?{$i}")
                ->setParameter($i, $parameter);
        }
        return $queryBuilder->getQuery()->getResult();
    }

    public function getOnsiteIncentives($site, $params): array
    {
        $queryBuilder = $this->createQueryBuilder('i')
            ->select('i.createdTs, i.participantId, i.incentiveDateGiven, i.incentiveOccurrence,
                i.otherIncentiveOccurrence, i.incentiveType, i.otherIncentiveType, i.incentiveAmount, i.giftCardType,
                i.declined, i.notes, au.email as amendedUser, u.email, ii.id as importId, i.Recipient as recipient,
                 i.numberOfItems as numberOfItems, i.typeOfItem as typeOfItem')
            ->leftJoin('i.user', 'u')
            ->leftJoin('i.amendedUser', 'au')
            ->leftJoin('i.import', 'ii')
            ->where('i.site =:site')
            ->andWhere('i.cancelledUser is null');

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
                ->setParameter('endDate', $params['endDate']->modify('+1 day'));
        }

        if (!empty($params['startDateOfService'])) {
            $queryBuilder->andWhere('i.incentiveDateGiven >= :startDateOfService')
                ->setParameter('startDateOfService', $params['startDateOfService']);
        }

        if (!empty($params['endDateOfService'])) {
            $queryBuilder->andWhere('i.incentiveDateGiven <= :endDateOfService')
                ->setParameter('endDateOfService', $params['endDateOfService']);
        }

        $queryBuilder->setParameter('site', $site);

        if (isset($params['sortColumn'])) {
            $queryBuilder->orderBy($params['sortColumn'], $params['sortDir']);
        } else {
            $queryBuilder->orderBy('i.createdTs', 'DESC');
        }

        if (isset($params['start'])) {
            return $queryBuilder
                ->getQuery()
                ->setFirstResult($params['start'])
                ->setMaxResults($params['length'])
                ->getResult();
        }
        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function getOnsiteIncentivesCount($site, $params): int
    {
        $queryBuilder = $this->createQueryBuilder('i')
            ->select('count(i.id)')
            ->leftJoin('i.user', 'u')
            ->leftJoin('i.amendedUser', 'au')
            ->leftJoin('i.import', 'ii')
            ->where('i.site =:site')
            ->andWhere('i.cancelledUser is null');
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
                ->setParameter('endDate', $params['endDate']->modify('+1 day'));
        }

        if (!empty($params['startDateOfService'])) {
            $queryBuilder->andWhere('i.incentiveDateGiven >= :startDateOfService')
                ->setParameter('startDateOfService', $params['startDateOfService']);
        }

        if (!empty($params['endDateOfService'])) {
            $queryBuilder->andWhere('i.incentiveDateGiven <= :endDateOfService')
                ->setParameter('endDateOfService', $params['endDateOfService']);
        }

        $queryBuilder->setParameter('site', $site);

        return $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getActiveIncentivesIncludingRelated(Participant $participant)
    {
        $queryBuilder = $this->createQueryBuilder('i')
            ->select('i')
            ->andWhere('i.participantId = :participantid or i.relatedParticipantRecipient = :participantid')
            ->andWhere('i.cancelledTs is null')
            ->setParameter('participantid', $participant->id)
            ->orderBy('i.createdTs', 'DESC');
        return $queryBuilder->getQuery()->getResult();
    }

    public function getPediatricIncentivesForReport(\DateTime $startDate, \DateTime $endDate)
    {
        $queryBuild = $this->createQueryBuilder('i')
            ->select('i.participantId, i.createdTs, i.site, i.Recipient, i.incentiveDateGiven,
            i.incentiveOccurrence, i.incentiveType, i.giftCardType, i.typeOfItem, i.numberOfItems,
            i.incentiveAmount, i.declined')
            ->orWhere('i.Recipient in (:recipients)')
            ->orWhere('i.incentiveOccurrence = :incentive_occurrence')
            ->setParameter('incentive_occurrence', Incentive::PEDIATRIC_VISIT)
            ->setParameter('recipients', [Incentive::PEDIATRIC_GUARDIAN, Incentive::PEDIATRIC_PARTICIPANT, Incentive::OTHER])
            ->getQuery();
        return $queryBuild->getResult();
    }
}
