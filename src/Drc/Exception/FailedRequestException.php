<?php

namespace App\Drc\Exception;

class FailedRequestException extends \Exception implements ParticipantSearchExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Search request failed: failed request');
    }
}
