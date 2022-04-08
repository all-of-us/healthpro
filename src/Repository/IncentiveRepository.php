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
            ->orderBy('i.giftCardType', 'ASC')
            ->setMaxResults(10);

        foreach ($queryParts as $i => $queryPart) {
            $parameter = "%{$queryPart}%";
            $queryBuilder
                ->andWhere("i.giftCardType like ?{$i}")
                ->setParameter($i, $parameter);
        }
        return $queryBuilder->getQuery()->getResult();
    }
}
