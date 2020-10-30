<?php

namespace App\Repository;

use App\Entity\Evaluation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Evaluation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Evaluation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Evaluation[]    findAll()
 * @method Evaluation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EvaluationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evaluation::class);
    }

     /**
      * @return Evaluation[] Returns an array of Evaluation objects
      */
    public function getMissingEvaluations()
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.history', 'eh')
            ->where('e.finalizedTs is not null')
            ->andWhere('e.rdrId is null')
            ->andWhere('eh.type != :type OR eh.type is null')
            ->setParameter('type', 'cancel')
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
