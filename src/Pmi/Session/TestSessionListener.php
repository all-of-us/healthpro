<?php

namespace Pmi\Session;

use Pimple\Container;
use Symfony\Component\HttpKernel\EventListener\TestSessionListener as BaseTestSessionListener;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class TestSessionListener extends BaseTestSessionListener
{
    private $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    protected function getSession(): ?SessionInterface
    {
        if (!isset($this->app['session'])) {
            return null;
        }

        return $this->app['session'];
    }
}
