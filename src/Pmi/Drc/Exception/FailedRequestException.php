<?php
namespace Pmi\Drc\Exception;

class FailedRequestException extends \Exception implements ParticipantSearchExceptionInterface
{
    protected $message = 'Search request failed: failed request';
}
