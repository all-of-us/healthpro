<?php

namespace App\Drc\Exception;

class InvalidResponseException extends \Exception implements ParticipantSearchExceptionInterface
{
    protected $message = 'Search request failed: invalid response';
}
