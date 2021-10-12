<?php

namespace App\Entities;

use App\Datastore\Entity;

class Configuration extends Entity
{
    protected $excludeIndexes = ['value'];

    protected static function getKind()
    {
        return 'Configuration';
    }
}
