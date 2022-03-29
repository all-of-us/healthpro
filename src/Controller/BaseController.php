<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BaseController extends AbstractController
{
    protected function isReadOnly(): bool
    {
        $route = $this->container->get('request_stack')->getCurrentRequest()->get('_route');
        return strpos($route, 'read_') === 0;
    }

    protected function getUserEntity()
    {
        $em = $this->container->get('doctrine')->getManager();
        return $em->getRepository(User::class)->find($this->getUser()->getId());
    }
}
