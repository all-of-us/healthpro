<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class LegacyRedirectController extends AbstractController
{
    /**
     * @Route("/s/{url}", requirements={"url"=".*"})
     */
    public function legacyRedirectAction($url)
    {
        // Use 308 response code for permanent redirect that maintains request method
        return $this->redirect("/{$url}", 308);
    }
}
