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
}
