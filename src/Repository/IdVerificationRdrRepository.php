<?php

namespace App\Repository;

use App\Entity\IdVerificationRdr;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IdVerificationRdr>
 */
class IdVerificationRdrRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IdVerificationRdr::class);
    }

    /**
     * @return array<int, IdVerificationRdr>
     */
    public function getIdVerificationsRdr(int $limit): array
    {
        return $this->createQueryBuilder('ivr')
            ->where('ivr.insertId is null')
            ->setMaxResults($limit)
            ->orderBy('ivr.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
