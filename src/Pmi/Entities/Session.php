<?php
namespace Pmi\Entities;

use Pmi\Datastore\Entity;

class Session extends Entity
{
    protected $excludeIndexes = ['data'];

    protected static function getKind()
    {
        return 'Session';
    }
}
