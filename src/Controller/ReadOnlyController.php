<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/read")
 */
class ReadOnlyController extends AbstractController
{
    /**
     * @Route("/", name="read_home")
     */
    public function indexAction(): Response
    {
        return $this->render('readonly/index.html.twig');
    }
}
