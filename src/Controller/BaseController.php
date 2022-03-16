<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BaseController extends AbstractController
{
    protected function isReadOnly(): bool
    {
        return strpos($this->container->get('request_stack')->getCurrentRequest()->get('_route'), 'read_') === 0 ? true : false;
    }
}
