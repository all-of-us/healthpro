<?php

namespace App\Entities;

use App\Datastore\Entity;

class Cache extends Entity
{
    protected $excludeIndexes = ['data'];

    protected static function getKind()
    {
        return 'Cache';
    }
}
