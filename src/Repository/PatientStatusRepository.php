<?php

namespace App\Repository;

use App\Entity\PatientStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PatientStatus|null find($id, $lockMode = null, $lockVersion = null)
 * @method PatientStatus|null findOneBy(array $criteria, array $orderBy = null)
 * @method PatientStatus[]    findAll()
 * @method PatientStatus[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PatientStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PatientStatus::class);
    }

    public function getOrgPatientStatusData($participantId, $organizationId)
    {
        $query = "
            SELECT ps.id as ps_id,
                   ps.organization,
                   ps.awardee,
                   psh.id as psh_id,
                   psh.user_id,
                   psh.site,
                   psh.comments,
                   psh.status,
                   psh.created_ts,
                   s.name as site_name,
                   u.email as user_email
            FROM patient_status ps
            LEFT JOIN patient_status_history psh ON ps.history_id = psh.id
            LEFT JOIN sites s ON psh.site = s.site_id
            LEFT JOIN users u ON psh.user_id = u.id
            WHERE ps.participant_id = :participantId
              AND ps.organization = :organization
            ORDER BY ps.id DESC
        ";
        $data = $this->getEntityManager()->getConnection()->fetchAll($query, [
            'participantId' => $participantId,
            'organization' => $organizationId
        ]);
        if (!empty($data)) {
            $data[0]['display_status'] = array_search($data[0]['status'], PatientStatus::$patientStatus);
            return $data[0];
        }
        return null;
    }

    public function getOrgPatientStatusHistoryData($participantId, $organization)
    {
        $query = "
            SELECT ps.id as ps_id,
                   ps.organization,
                   ps.awardee,
                   psh.id as psh_id,
                   psh.user_id,
                   psh.site,
                   psh.comments,
                   psh.status,
                   psh.created_ts,
                   psh.import_id,
                   s.name as site_name,
                   u.email as user_email
            FROM patient_status ps
            LEFT JOIN patient_status_history psh ON ps.id = psh.patient_status_id
            LEFT JOIN sites s ON psh.site = s.site_id
            LEFT JOIN users u ON psh.user_id = u.id
            WHERE ps.participant_id = :participantId
              AND ps.organization = :organization
            ORDER BY psh.id DESC
        ";
        $results = $this->getEntityManager()->getConnection()->fetchAll($query, [
            'participantId' => $participantId,
            'organization' => $organization
        ]);
        if (!empty($results)) {
            foreach ($results as $key => $result) {
                $results[$key]['display_status'] = array_search($result['status'], PatientStatus::$patientStatus);
            }
        }
        return $results;
    }

    public function getAwardeePatientStatusData($participantId, $organization)
    {
        $query = "
            SELECT ps.id as ps_id,
                   ps.organization,
                   ps.awardee,
                   psh.id as psh_id,
                   psh.user_id,
                   psh.site,
                   psh.comments,
                   psh.status,
                   psh.created_ts,
                   s.name as site_name,
                   u.email as user_email,
                   o.name as organization_name,
                   a.name as awardee_name
            FROM patient_status ps
            LEFT JOIN patient_status_history psh ON ps.history_id = psh.id
            LEFT JOIN sites s ON psh.site = s.site_id
            LEFT JOIN users u ON psh.user_id = u.id
            LEFT JOIN organizations o ON ps.organization = o.id
            LEFT JOIN awardees a ON ps.awardee = a.id
            WHERE ps.participant_id = :participantId
              AND ps.organization != :organization
            ORDER BY ps.id DESC
        ";
        $results = $this->getEntityManager()->getConnection()->fetchAll($query, [
            'participantId' => $participantId,
            'organization' => $organization
        ]);
        if (!empty($results)) {
            foreach ($results as $key => $result) {
                $results[$key]['display_status'] = array_search($result['status'], PatientStatus::$patientStatus);
            }
        }
        return $results;
    }

    public function getOnsitePatientStatuses($params, $awardee): array
    {
        $queryBuilder = $this->createQueryBuilder('ps')
            ->select('ps.participantId, psh.site, psh.status, psh.comments, psh.createdTs, u.email')
            ->leftJoin('ps.history', 'psh')
            ->leftJoin('App\Entity\User', 'u', Join::WITH, 'psh.userId = u.id')
            ->where('ps.awardee =:awardee');

        if (!empty($params['startDate'])) {
            $queryBuilder->andWhere('psh.createdTs >= :startDate')
                ->setParameter('startDate', $params['startDate']);
        }

        if (!empty($params['endDate'])) {
            $queryBuilder->andWhere('psh.createdTs <= :endDate')
                ->setParameter('endDate', $params['endDate']);
        }

        return $queryBuilder
            ->setParameter('awardee', $awardee)
            ->orderBy('ps.id', 'ASC')
            ->getQuery()
            ->setFirstResult($params['start'])
            ->setMaxResults($params['length'])
            ->getResult();
    }

    public function getOnsitePatientStatusesCount(): int
    {
        return $this->createQueryBuilder('ps')
            ->select('count(psh.id)')
            ->leftJoin('ps.history', 'psh')
            ->where('ps.organization =:organization')
            ->setParameter('organization', 'PITT_UPMC')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
