<?php

namespace App\Repository;

use App\Entity\ZScores;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ZScores>
 *
 * @method ZScores|null find($id, $lockMode = null, $lockVersion = null)
 * @method ZScores|null findOneBy(array $criteria, array $orderBy = null)
 * @method ZScores[]    findAll()
 * @method ZScores[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ZScoresRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZScores::class);
    }

    public function save(ZScores $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ZScores $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
