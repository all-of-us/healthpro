<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class ReadOnlyService
{
    protected $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function isReadOnly()
    {
        return strpos($this->requestStack->getCurrentRequest()->get('_route'), 'read_') === 0 ? true : false;
    }
}
