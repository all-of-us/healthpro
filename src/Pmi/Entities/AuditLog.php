<?php
namespace Pmi\Entities;

use Pmi\Datastore\Entity;

class AuditLog extends Entity
{
    protected $excludeIndexes = ['data'];

    protected static function getKind()
    {
        return 'AuditLog';
    }
}
