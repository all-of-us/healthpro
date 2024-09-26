<?php

namespace App\Repository;

use App\Entity\NphDlw;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NphDlw>
 *
 * @method NphDlw|null find($id, $lockMode = null, $lockVersion = null)
 * @method NphDlw|null findOneBy(array $criteria, array $orderBy = null)
 * @method NphDlw[]    findAll()
 * @method NphDlw[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NphDlwRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NphDlw::class);
    }

    public function save(NphDlw $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(NphDlw $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getDlwWithMissingRdrId(int $limit): array
    {
        $queryBuilder = $this->createQueryBuilder('nd')
            ->where('nd.rdrId is null')
            ->setMaxResults($limit);
        return $queryBuilder->getQuery()->getResult();
    }
}
