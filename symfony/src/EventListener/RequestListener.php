<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use App\Service\LoggerService;

class RequestListener
{
    private $logger;

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $this->logRequest();
    }

    private function logRequest()
    {
        $this->logger->log(LoggerService::REQUEST);
    }
}
