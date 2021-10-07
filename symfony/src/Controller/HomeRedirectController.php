<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class HomeRedirectController extends AbstractController
{
    /**
     * @Route("/")
     */
    public function homeRedirectAction()
    {
        return $this->redirectToRoute('home');
    }
}
