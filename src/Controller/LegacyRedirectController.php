<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;

class LegacyRedirectController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    #[Route(path: '/s/{url}', requirements: ['url' => '.*'])]
    public function legacyRedirectAction($url)
    {
        // Use 308 response code for permanent redirect that maintains request method
        return $this->redirect("/{$url}", 308);
    }
}
