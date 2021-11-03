<?php

namespace App\Datastore\Exception;

class UnknownEntityFieldException extends \Exception
{
    public function __construct($schema, $field)
    {
        $message = "Unknown field \"{$field}\" in entity for schema \"{$schema}\"";
        parent::__construct($message);
    }
}
