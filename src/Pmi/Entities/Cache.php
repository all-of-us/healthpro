<?php
namespace Pmi\Entities;

use Pmi\Datastore\Entity;

class Cache extends Entity
{
    protected $excludeIndexes = ['data'];

    protected static function getKind()
    {
        return 'Cache';
    }
}
