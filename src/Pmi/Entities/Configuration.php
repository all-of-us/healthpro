<?php
namespace Pmi\Entities;

use GDS\Schema;
use Pmi\Datastore\Entity;

class Configuration extends Entity
{
    protected static function buildSchema() {
        return (new Schema('Configuration'))
            ->addString('key')
            ->addString('value', false);
    }
}
