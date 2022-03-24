<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BaseController extends AbstractController
{
    protected function isReadOnly(): bool
    {
        $route = $this->container->get('request_stack')->getCurrentRequest()->get('_route');
        return strpos($route, 'read_') === 0;
    }
}
