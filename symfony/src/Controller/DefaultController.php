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
     * @Route("/", name="home")
     */
    public function index(LoggerService $loggerService)
    {
        $loggerService->log(Log::REQUEST, 'HealthPro Symfony Home Page');
        return $this->render('index.html.twig');
    }
}
