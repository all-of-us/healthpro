<?php

namespace App\Datastore\Entities;

use App\Datastore\Entity;

class Configuration extends Entity
{
    protected $excludeIndexes = ['value'];

    protected static function getKind()
    {
        return 'Configuration';
    }
}
