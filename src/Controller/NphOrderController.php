<?php

namespace App\Controller;

use App\Form\Nph\NphOrderType;
use App\Nph\Order\Samples;
use App\Service\Nph\NphOrderService;
use App\Service\ParticipantSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/nph")
 */
class NphOrderController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    /**
     * @Route("/participant/{participantId}/order/module/{module}/visit/{visit}", name="nph_generate_oder")
     */
    public function generateOrderAction(
        $participantId,
        $module,
        $visit,
        NphOrderService $nphOrderService,
        ParticipantSummaryService $participantSummaryService,
        Request $request
    ): Response {
        $participant = $participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $nphOrderService->loadModules($module, $visit, $participantId);
        $timePointSamples = $nphOrderService->getTimePointSamples();
        $timePoints = $nphOrderService->getTimePoints();
        $oderForm = $this->createForm(
            NphOrderType::class,
            null,
            ['timePointSamples' => $timePointSamples, 'timePoints' => $timePoints, 'stoolSamples' => $nphOrderService->getStoolSamples()]
        );
        $oderForm->handleRequest($request);
        if ($oderForm->isSubmitted() && $oderForm->isValid()) {
            $formData = $oderForm->getData();
            $nphOrderService->createOrdersAndSamples($formData);
            $this->addFlash('success', 'Orders Created');
        }
        return $this->render('program/nph/order/generate-orders.html.twig', [
            'orderForm' => $oderForm->createView(),
            'timePointSamples' => $timePointSamples,
            'participant' => $participant,
            'module' => $module,
            'visit' => $visit,
            'stoolSamples' => $nphOrderService->getStoolSamples(),
            'nailSamples' => $nphOrderService->getNailSamples()
        ]);
    }
}
