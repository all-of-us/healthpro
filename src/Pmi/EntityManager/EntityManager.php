<?php
namespace Pmi\EntityManager;

class EntityManager
{
    protected $dbal;

    protected $entities = [
        'users' => 'doctrine',
        'orders' => 'doctrine',
        'evaluations' => 'doctrine'
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
            case 'doctrine':
                if (!$this->dbal) {
                    throw new \Exception('No DBAL available');
                }
                return new DoctrineRepository($this->dbal, $entity);

            default:
                throw new \Exception('Invalid entity type');
        }
    }
}
