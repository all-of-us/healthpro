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
     * @Route("/module/{module}/visit/{visit}", name="nph_generate_oder")
     */
    public function generateOrderAction($module, $visit): Response
    {
        $moduleClass = 'App\Nph\Order\Module' .$module . $visit;
        $module = new $moduleClass();
        //dd($module->getTimePointsWithSamples());
        return $this->render('program/nph/order/generate-orders.html.twig');
    }
}
