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
     * @Route("/module/{module}/visit/{visit}/{color}", name="nph_generate_oder")
     */
    public function generateOrderAction($module, $visit, $color = null): Response
    {
        $moduleClass = 'App\Nph\Module' .$module . $visit;
        $module = new $moduleClass($color);
        //dd($module->getTimePointsWithSamples());
        return $this->render('program/nph/order/generate-orders.html.twig');
    }
}
