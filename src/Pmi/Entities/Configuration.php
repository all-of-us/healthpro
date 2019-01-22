<?php
namespace Pmi\Entities;

use Pmi\Datastore\Entity;

class Configuration extends Entity
{
    protected static function getKind()
    {
        return 'Configuration';
    }
}
