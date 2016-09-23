<?php
namespace Pmi\Entities;

use GDS\Schema;
use Pmi\Datastore\Entity;

class AuditLog extends Entity
{
    protected static function buildSchema()
    {
        return (new Schema('AuditLog'))
            ->addString('action')
            ->addString('user')
            ->addDatetime('timestamp')
            ->addString('ip')
            ->addString('data', false);
    }
}
