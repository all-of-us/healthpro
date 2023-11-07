<?php

namespace App\Repository;

use App\Entity\HeadCircumferenceForAge0To36Months;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HeadCircumferenceForAge0To36Months>
 *
 * @method HeadCircumferenceForAge0To36Months|null find($id, $lockMode = null, $lockVersion = null)
 * @method HeadCircumferenceForAge0To36Months|null findOneBy(array $criteria, array $orderBy = null)
 * @method HeadCircumferenceForAge0To36Months[]    findAll()
 * @method HeadCircumferenceForAge0To36Months[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HeadCircumferenceForAge0To36MonthsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HeadCircumferenceForAge0To36Months::class);
    }

    public function save(HeadCircumferenceForAge0To36Months $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(HeadCircumferenceForAge0To36Months $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getChartsData($sex): ?array
    {
        $queryBuilder = $this->createQueryBuilder('hca');
        if ($sex) {
            $queryBuilder->where('hca.sex = :sex')
                ->setParameter('sex', $sex);
        }
        return $queryBuilder->getQuery()->getArrayResult();
    }
}
