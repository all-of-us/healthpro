<?php
namespace Pmi\EntityManager;

use Pmi\Util;

class EntityManager
{
    protected $dbal;

    // Define custom repositories
    protected $entities = [
        'order_repository' => 'OrderRepository',
        'evaluation_repository' => 'EvaluationRepository',
        'problem_repository' => 'ProblemRepository'
    ];

    protected $timezone;

    public function setDbal($dbal)
    {
        $this->dbal = $dbal;
    }

    public function getRepository($entity) {
        if (!$this->dbal) {
            throw new \Exception('No DBAL available');
        }
        if (isset($this->entities[$entity])) {
            $repository = __NAMESPACE__ . '\\' . $this->entities[$entity];
            return new $repository($this->dbal, $entity, $this->getTimezone());
        } else {
            return new DoctrineRepository($this->dbal, $entity, $this->getTimezone());
        }
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
