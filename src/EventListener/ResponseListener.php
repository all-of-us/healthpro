<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;

class ResponseListener
{
    use ResponseSecurityHeadersTrait;

    public function onKernelResponse(ResponseEvent $event)
    {
        $response = $event->getResponse();
        $this->addSecurityHeaders($response);
    }
}
