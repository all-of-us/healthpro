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
        return $this->redirect("/{$url}");
    }
}
