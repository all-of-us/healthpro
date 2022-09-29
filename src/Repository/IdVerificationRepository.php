<?php

namespace App\Repository;

use App\Entity\IdVerification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method IdVerification|null find($id, $lockMode = null, $lockVersion = null)
 * @method IdVerification|null findOneBy(array $criteria, array $orderBy = null)
 * @method IdVerification[]    findAll()
 * @method IdVerification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IdVerificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IdVerification::class);
    }

    public function getOnsiteIdVerifications($site, $params): array
    {
        $queryBuilder = $this->createQueryBuilder('idv')
            ->select('idv.createdTs, idv.participantId, idv.verificationType, idv.visitType, 
                u.email, idvi.id as importId');

        $this->setQueryBuilder($queryBuilder, $params, $site);

        if (isset($params['sortColumn'])) {
            $queryBuilder->orderBy($params['sortColumn'], $params['sortDir']);
        } else {
            $queryBuilder->orderBy('idv.createdTs', 'DESC');
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

    public function getOnsiteIdVerificationsCount($site, $params): int
    {
        $queryBuilder = $this->createQueryBuilder('idv')
            ->select('count(idv.id)');

        $this->setQueryBuilder($queryBuilder, $params, $site);

        return $queryBuilder
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function setQueryBuilder(&$queryBuilder, $params, $site): void
    {
        $queryBuilder->leftJoin('idv.user', 'u')
            ->leftJoin('idv.import', 'idvi')
            ->where('idv.site =:site');

        if (!empty($params['participantId'])) {
            $queryBuilder->andWhere('idv.participantId = :participantId')
                ->setParameter('participantId', $params['participantId']);
        }

        if (!empty($params['startDate'])) {
            $queryBuilder->andWhere('idv.createdTs >= :startDate')
                ->setParameter('startDate', $params['startDate']);
        }

        if (!empty($params['endDate'])) {
            $queryBuilder->andWhere('idv.createdTs <= :endDate')
                ->setParameter('endDate', $params['endDate']->modify('+1 day'));
        }

        $queryBuilder->setParameter('site', $site);
    }
}
