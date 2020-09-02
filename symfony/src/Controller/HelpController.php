<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s/help")
 */
class HelpController extends AbstractController
{
    /**
     * @Route("/", name="help_home")
     */
    public function index()
    {
        return $this->render('help/index.html.twig');
    }
}
