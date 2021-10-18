<?php

namespace App\Datastore\Entities;

use App\Datastore\Entity;

class AuditLog extends Entity
{
    protected $excludeIndexes = ['data'];

    protected static function getKind()
    {
        return 'AuditLog';
    }
}
