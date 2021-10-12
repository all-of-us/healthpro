<?php
namespace Pmi\EntityManager;

use App\Util;

class EntityManager
{
    protected $dbal;

    protected $timezone;

    public function setDbal($dbal)
    {
        $this->dbal = $dbal;
    }

    public function getRepository($entity) {
        if (!$this->dbal) {
            throw new \Exception('No DBAL available');
        }
        return new DoctrineRepository($this->dbal, $entity, $this->getTimezone());
    }

    public function fetchAll($query, $parameters)
    {
        $result = $this->dbal->fetchAll($query, $parameters);
        return Util::parseMultipleTimestamps($result, $this->timezone);
    }

    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;
    }

    public function getTimezone()
    {
        return $this->timezone;
    }
}
