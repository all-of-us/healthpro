<?php
namespace Pmi\Entities;

use GDS\Schema;
use Pmi\Datastore\Entity;

class Session extends Entity
{
    protected static function buildSchema() {
        return (new Schema('Session'))
            ->addDatetime('modified')
            ->addString('data', false);
    }
}
