<?php
namespace Pmi\Service;

use DateTime;

class NoticeService
{
    private $em;
    private $timezone;

    public function __construct($entityManager)
    {
        $this->em = $entityManager;
    }

    protected function patternToRegex($pattern)
    {
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

    public function getCurrentNotices($url)
    {
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $notices = $this->em->getRepository('notices')->fetchBySql(
            '(start_ts is null OR start_ts <= ?) AND ' . 
            '(end_ts is null OR end_ts >= ?)',
            [$now, $now]
        );

        $matches = [];
        foreach ($notices as $notice) {
            $regex = $this->patternToRegex($notice['url']);
            if (preg_match($regex, $url)) {
                $matches[] = $notice;
            }
        }

        return $matches;
    }
}
