<?php

namespace App\Datastore\Entities;

use App\Datastore\Entity;

class Configuration extends Entity
{
    /** @var list<string> */
    protected array $excludeIndexes = ['value'];

    protected static function getKind(): string
    {
        return 'Configuration';
    }
}
