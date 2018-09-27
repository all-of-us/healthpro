<?php
namespace Pmi\EntityManager;

class EntityManager
{
    protected $dbal;

    protected $entities = [
        'users' => 'doctrine',
        'orders' => 'doctrine',
        'evaluations' => 'doctrine',
        'sites' => 'doctrine',
        'withdrawal_log' => 'doctrine',
        'problems' => 'doctrine',
        'problem_comments' => 'doctrine',
        'evaluations_queue' => 'doctrine',
        'organizations' => 'doctrine',
        'awardees' => 'doctrine',
        'missing_notifications_log' => 'doctrine',
        'orders_history' => 'doctrine'
    ];

    protected $timezone;

    public function setDbal($dbal)
    {
        $this->dbal = $dbal;
    }

    public function getRepository($entity) {
        if (!array_key_exists($entity, $this->entities)) {
            throw new \Exception('Entity not defined');
        }
        switch ($this->entities[$entity]) {
            case 'doctrine':
                if (!$this->dbal) {
                    throw new \Exception('No DBAL available');
                }
                return new DoctrineRepository($this->dbal, $entity, $this->getTimezone());

            default:
                throw new \Exception('Invalid entity type');
        }
    }

    public function fetchAll($query, $parameters)
    {
        $result = $this->dbal->fetchAll($query, $parameters);
        return $this->parseMultipleTimestamps($result);
    }

    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;
    }

    public function getTimezone()
    {
        return $this->timezone;
    }

    protected function parseMultipleTimestamps(array $result)
    {
        foreach ($result as $key => $value) {
            $result[$key] = $this->parseTimestamps($value);
        }
        return $result;
    }

    protected function parseTimestamps(array $result)
    {
        foreach ($result as $key => $value) {
            if (null !== $value && substr($key, -3, 3) == '_ts' && preg_match("/^\d{4}\-\d{2}\-\d{2}/", $value)) {
                $result[$key] = \DateTime::createFromFormat('Y-m-d H:i:s', $value)->setTimezone(new \DateTimeZone($this->timezone));
            }
        }
        return $result;
    }

    protected function dateTimesToStrings(array $data)
    {
        foreach ($data as $key => $value) {
            if ($value instanceof \DateTime) {
                $value->setTimezone(new \DateTimezone('UTC'));
                $data[$key] = $value->format('Y-m-d H:i:s');
            }
        }
        return $data;
    }
}
