<?php
namespace Pmi\Entities;

use Pmi\Datastore\Entity;

class Session extends Entity
{
    protected static function getKind()
    {
        return 'Session';
    }
}
