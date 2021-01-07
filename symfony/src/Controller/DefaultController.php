<?php

namespace App\Controller;

use App\Service\LoggerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Pmi\Audit\Log;

/**
 * @Route("/s")
 */
class DefaultController extends AbstractController
{
    /**
     * @Route("/", name="symfony_home")
     */
    public function index()
    {
        return $this->render('index.html.twig');
    }

    /**
     * @Route("/admin", name="admin_home")
     */
    public function adminIndex()
    {
        return $this->render('admin/index.html.twig');
    }
}
