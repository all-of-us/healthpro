<?php

namespace App\Repository;

use App\Entity\Awardee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Awardee>
 */
class AwardeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Awardee::class);
    }

    public function deleteAwardees(): int
    {
        return $this->createQueryBuilder('a')
            ->delete()
            ->getQuery()
            ->execute()
        ;
    }
}
