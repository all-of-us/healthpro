<?php

namespace App\Controller;

use App\Entity\NphOrder;
use App\Form\Nph\NphOrderCollectType;
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
        $ordersData = $nphOrderService->getExistingOrdersData();
        $oderForm = $this->createForm(
            NphOrderType::class,
            $ordersData,
            ['timePointSamples' => $timePointSamples, 'timePoints' => $timePoints, 'stoolSamples' =>
                $nphOrderService->getSamplesByType('stool')]
        );
        $oderForm->handleRequest($request);
        if ($oderForm->isSubmitted() && $oderForm->isValid()) {
            $formData = $oderForm->getData();
            $nphOrderService->createOrdersAndSamples($formData);
            $this->addFlash('success', 'Orders Created');
            // TODO: Redirect to generate orders & print labels page
            return $this->redirectToRoute('nph_generate_oder', ['participantId' => $participantId, 'module' => $module,
                'visit' => $visit]);
        }
        return $this->render('program/nph/order/generate-orders.html.twig', [
            'orderForm' => $oderForm->createView(),
            'timePointSamples' => $timePointSamples,
            'participant' => $participant,
            'module' => $module,
            'visit' => $visit,
            'timePoints' => $nphOrderService->getTimePoints(),
            'samples' => $nphOrderService->getSamples(),
            'stoolSamples' => $nphOrderService->getSamplesByType('stool'),
            'nailSamples' => $nphOrderService->getSamplesByType('nail'),
            'samplesOrderIds' => $nphOrderService->getSamplesWithOrderIds()
        ]);
    }

    /**
     * @Route("/participant/{participantId}/order/{orderId}/collect", name="nph_order_collect")
     */
    public function orderCollectAction(
        $participantId,
        $orderId,
        NphOrderService $nphOrderService,
        ParticipantSummaryService $participantSummaryService,
        Request $request
    ): Response {
        $participant = $participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $order = $this->em->getRepository(NphOrder::class)->find($orderId);
        if (empty($order)) {
            throw $this->createNotFoundException('Order not found.');
        }
        $nphOrderService->loadModules($order->getModule(), $order->getVisitType(), $participantId);
        $sampleLabels = $nphOrderService->getSamplesWithLabels($order->getNphSamples());
        $orderCollectionData = $nphOrderService->getExistingOrderCollectionData($order);
        $oderCollectForm = $this->createForm(
            NphOrderCollectType::class,
            $orderCollectionData,
            ['samples' => $sampleLabels, 'orderType' => $order->getOrderType(), 'timeZone' => $this->getSecurityUser()->getTimezone()]
        );
        $oderCollectForm->handleRequest($request);
        if ($oderCollectForm->isSubmitted() && $oderCollectForm->isValid()) {
            $formData = $oderCollectForm->getData();
            if ($nphOrderService->saveOrderCollection($formData, $order)) {
                $this->addFlash('success', 'Order collection saved');
            } else {
                $this->addFlash('error', 'Order collection failed');
            }
        }
        return $this->render('program/nph/order/collect.html.twig', [
            'order' => $order,
            'orderCollectForm' => $oderCollectForm->createView(),
            'participant' => $participant,
            'timePoints' => $nphOrderService->getTimePoints(),
            'samples' => $nphOrderService->getSamples(),
        ]);
    }

    /**
     * @Route("/participant/{participantId}/order/module/{module}/visit/{visit}/OrderSummary", name="nph_order_summary")
     */
    public function orderSummary($participantId, $module, $visit, ParticipantSummaryService $participantSummaryService, NphOrderService $nphOrderService): Response
    {
        $participant = $participantSummaryService->getParticipantById($participantId);
        $nphOrderService->loadModules($module, $visit, $participantId);
        $orderInfo = $nphOrderService->getParticipantOrderSummaryByModuleAndVisit($participantId, $module, $visit);
        return $this->render(
            'program/nph/order/summary.html.twig',
            ['participant' => $participant,
             'orderSummary' => $orderInfo,
                'module' => $module,
                'visit' => $visit]
        );
    }
}
