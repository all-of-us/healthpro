<?php

namespace App\Datastore\Entities;

use App\Datastore\Entity;

class AuditLog extends Entity
{
    /** @var list<string> */
    protected array $excludeIndexes = ['data'];

    protected static function getKind(): string
    {
        return 'AuditLog';
    }
}
