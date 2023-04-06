<?php

namespace App\Repository;

use App\Entity\Awardee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Awardee|null find($id, $lockMode = null, $lockVersion = null)
 * @method Awardee|null findOneBy(array $criteria, array $orderBy = null)
 * @method Awardee[]    findAll()
 * @method Awardee[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AwardeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Awardee::class);
    }

    public function deleteAwardees()
    {
        return $this->createQueryBuilder('a')
            ->delete()
            ->getQuery()
            ->execute()
        ;
    }
}
