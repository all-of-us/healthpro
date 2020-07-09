<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpFoundation\Response;

class ResponseListener
{
    use ResponseSecurityHeadersTrait;

    public function onKernelResponse(ResponseEvent $event)
    {
        $response = $event->getResponse();
        $this->addSecurityHeaders($response);
    }
}
