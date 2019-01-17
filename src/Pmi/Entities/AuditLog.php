<?php
namespace Pmi\Entities;

use Pmi\Datastore\Entity;

class AuditLog extends Entity
{
    protected static function getKind()
    {
        return 'AuditLog';
    }
}
