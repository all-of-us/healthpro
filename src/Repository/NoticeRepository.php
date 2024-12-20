<?php

namespace App\Repository;

use App\Entity\Notice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Notice|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notice|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notice[]    findAll()
 * @method Notice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NoticeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notice::class);
    }

    /**
     * @return Notice[] Returns an array of currently active Notice objects that match a URL path
     */
    public function getActiveNotices($path)
    {
        $currentNotices = $this->createQueryBuilder('n')
            ->where('n.status = true')
            ->andWhere('n.startTs is null OR n.startTs <= :now')
            ->andWhere('n.endTs is null OR n.endTs >= :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult()
        ;

        $matchingNotices = [];
        foreach ($currentNotices as $notice) {
            $regex = self::patternToRegex($notice->getUrl());
            if (preg_match($regex, $path)) {
                $matchingNotices[] = $notice;
            }
        }

        return $matchingNotices;
    }

    private static function patternToRegex($pattern)
    {
        $specialCases = [
            '/ppsc/participant/p' => '/^\/ppsc\/participant\/[^\/]+$/i',
            '/nph/participant/p' => '/^\/nph\/participant\/[^\/]+$/i'
        ];

        // if pattern matches a special case, return the corresponding regex
        if (isset($specialCases[$pattern])) {
            return $specialCases[$pattern];
        }

        // temporarily change wildcard asterisks to % to avoid escaping
        $regex = str_replace('*', '%', $pattern);
        // escape pattern for regex
        $regex = preg_quote($regex, '/');
        // replace wildcards with regex .*
        $regex = str_replace('%', '.*', $regex);
        // add delimeters, start and end characters, and case-insensitive modifier
        $regex = '/^' . $regex . '$/i';

        return $regex;
    }
}
