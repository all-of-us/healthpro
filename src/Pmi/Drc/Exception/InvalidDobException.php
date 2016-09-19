<?php
namespace Pmi\Drc\Exception;

class InvalidDobException extends \Exception implements ParticipantSearchExceptionInterface
{
    protected $message = 'Invalid date of birth format';
}
