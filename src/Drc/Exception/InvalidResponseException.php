<?php

namespace App\Drc\Exception;

class InvalidResponseException extends \Exception implements ParticipantSearchExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Search request failed: invalid response');
    }
}
