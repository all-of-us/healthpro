<?php

namespace App\Drc\Exception;

class InvalidDobException extends \Exception implements ParticipantSearchExceptionInterface
{
    public function __construct(string $message = 'Invalid date of birth format')
    {
        parent::__construct($message);
    }
}
