<?php

namespace App\Repository;

use App\Entity\PediatricAssent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PediatricAssent>
 *
 * @method PediatricAssent|null find($id, $lockMode = null, $lockVersion = null)
 * @method PediatricAssent|null findOneBy(array $criteria, array $orderBy = null)
 * @method PediatricAssent[]    findAll()
 * @method PediatricAssent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PediatricAssentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PediatricAssent::class);
    }
}
