<?php

namespace App\Controller;

use App\Form\Nph\NphOrderType;
use App\Service\Nph\NphOrderService;
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
    public function generateOrderAction($module, $visit, NphOrderService $nphOrderService): Response
    {
        $oderForm = $this->createForm(NphOrderType::class, null,
            ['timePointSamples' => $nphOrderService->getTimePointsWithSamples($module, $visit)]);
        return $this->render('program/nph/order/generate-orders.html.twig', ['orderForm' => $oderForm->createView()]);
    }
}
