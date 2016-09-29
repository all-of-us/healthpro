<?php
namespace Pmi\EntityManager;

class EntityManager
{
    protected $dbal;

    protected $entities = [
        'orders' => 'dbal',
        'evaluations' => 'dbal'
    ];

    public function setDbal($dbal)
    {
        $this->dbal = $dbal;
    }

    public function getRepository($entity) {
        if (!array_key_exists($entity, $this->entities)) {
            throw new \Exception('Entity not defined');
        }
        switch ($this->entities[$entity]) {
            case 'dbal':
                if (!$this->dbal) {
                    throw new \Exception('No DBAL available');
                }
                return new SqlRepository($this->dbal, $entity);

            default:
                throw new \Exception('Invalid entity type');
        }
    }
}
