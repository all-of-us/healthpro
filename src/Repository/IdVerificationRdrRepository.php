<?php

namespace App\Repository;

use App\Entity\IdVerificationRdr;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IdVerificationRdr>
 *
 * @method IdVerificationRdr|null find($id, $lockMode = null, $lockVersion = null)
 * @method IdVerificationRdr|null findOneBy(array $criteria, array $orderBy = null)
 * @method IdVerificationRdr[]    findAll()
 * @method IdVerificationRdr[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IdVerificationRdrRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IdVerificationRdr::class);
    }

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
