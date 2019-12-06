<?php
namespace Pmi\Entities;

use Pmi\Datastore\Entity;

class Cache extends Entity
{
    protected $excludeIndexes = ['data'];

    protected $limit = 500;

    protected static function getKind()
    {
        return 'Cache';
    }
}
