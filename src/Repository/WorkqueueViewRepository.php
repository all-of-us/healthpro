<?php

namespace App\Repository;

use App\Entity\WorkqueueView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method WorkqueueView|null find($id, $lockMode = null, $lockVersion = null)
 * @method WorkqueueView|null findOneBy(array $criteria, array $orderBy = null)
 * @method WorkqueueView[]    findAll()
 * @method WorkqueueView[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WorkqueueViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkqueueView::class);
    }


    // Sets default view to 0 for all user views except one
    public function updateDefaultView($id, $user)
    {
        $query = $this->createQueryBuilder('w')
            ->update('App\Entity\WorkqueueView', 'w')
            ->set('w.defaultView', 0)
            ->where('w.user = :user')
            ->andWhere('w.id != :id')
            ->setParameter('user', $user)
            ->setParameter('id', $id)
            ->getQuery();
        $query->execute();
    }
}
