<?php
namespace Pmi\Service;

use DateTime;
use Exception;

class MetricsService
{
    private $db;
    private $start;
    private $end;
    private $resolution;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function setRange($start, $end, $resolution)
    {
        $this->start = $start;
        $this->end = $end;
        $this->resolution = $resolution;
    }

    private function getEmptyResultsArray()
    {
        $results = [];

        // when we upgrade to PHP 7.3, this can be changed to $iterator = DateTime::createFromImmutable($this->start);
        $iterator = new DateTime();
        $iterator->setTimestamp($this->start->getTimestamp());

        switch ($this->resolution) {
            case 'hour':
                $format = 'Y-m-d H:00:00';
                $interval = '+1 hour';
                break;
            case 'day':
                $format = 'Y-m-d';
                $interval = '+1 day';
                break;
            case 'month':
                $format = 'Y-m-01';
                $interval = '+1 month';
                break;
            default:
                throw new Exception('Unknown resolution');
        }

        while ($iterator < $this->end) {
            $results[$iterator->format($format)] = 0;
            $iterator->modify($interval);
        }

        return $results;
    }

    private function getResults($metric)
    {
        $results = $this->getEmptyResultsArray();
        switch ($metric) {
            case 'orders':
                $table = 'orders';
                $column = 'created_ts';
                break;

            case 'physicalmeasurements':
                $table = 'evaluations';
                $column = 'created_ts';
                break;

            default:
                throw new Exception('Unknown metric');
        }

        switch ($this->resolution) {
            case 'hour':
                $dateTimeSelect = "date_format(created_ts, '%Y-%m-%d %H:00:00') as datetime";
                break;
            case 'day':
                $dateTimeSelect = 'date(created_ts) as datetime';
                break;
            case 'month':
                $dateTimeSelect = "date_format(created_ts, '%Y-%m-01') as datetime";
                break;
            default:
                throw new Exception('Unknown resolution');
        }

        $rows = $this->db->createQueryBuilder()
            ->select($dateTimeSelect, 'count(*) as count')
            ->from($table)
            ->where("{$column} >= :start and {$column} < :end")
            ->groupBy('datetime')
            ->setParameter('start', $this->start->format('Y-m-d H:i:s'))
            ->setParameter('end', $this->end->format('Y-m-d H:i:s'))
            ->execute()
            ->fetchAll();

        foreach ($rows as $row) {
            $results[$row['datetime']] = (int)$row['count'];
        }

        return $results;
    }

    public function getBiobankOrders()
    {
        return $this->getResults('orders');
    }

    public function getPhysicalMeasurements()
    {
        return $this->getResults('physicalmeasurements');
    }
}
