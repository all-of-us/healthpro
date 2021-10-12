<?php

namespace App\Entities;

use App\Datastore\Entity;

class Session extends Entity
{
    protected $excludeIndexes = ['data'];

    protected static function getKind()
    {
        return 'Session';
    }
}
