<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/nph/order")
 */
class NphOrderController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    /**
     * @Route("/module/{moduleType}/visit/{visitType}/{color}", name="nph_generate_oder")
     */
    public function generateOrderAction($moduleType, $visitType, $color = null): Response
    {
        $moduleClass = 'App\Nph\Module' . $visitType;
        $module = new $moduleClass($moduleType, $color);
        return $this->render('program/nph/order/generate-orders.html.twig');
    }
}
