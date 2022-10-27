<?php

namespace App\Controller;

use App\Nph\Module1;
use App\Nph\Module1MMTT;
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
        $module = new Module1MMTT();
        return $this->render('program/nph/order/generate-orders.html.twig');
    }
}
