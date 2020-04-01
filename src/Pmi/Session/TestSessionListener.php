<?php

namespace Pmi\Session;

use Pimple\Container;
use Symfony\Component\HttpKernel\EventListener\AbstractTestSessionListener;
use Symfony\Component\HttpFoundation\Session\SessionInterface;


class TestSessionListener extends AbstractTestSessionListener
{
    private $app;

    public function __construct(Container $app, array $sessionOptions = [])
    {
        $this->app = $app;
        parent::__construct($sessionOptions);
    }

    protected function getSession(): ?SessionInterface
    {
        if (!isset($this->app['session'])) {
            return null;
        }

        return $this->app['session'];
    }
}
