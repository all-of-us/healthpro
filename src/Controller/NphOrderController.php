<?php

namespace App\Controller;

use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Form\Nph\NphOrderCollectType;
use App\Form\Nph\NphOrderType;
use App\Form\Nph\SampleLookupType;
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
     * @Route("/samples/aliquot", name="nph_samples_aliquot")
     */
    public function sampleAliquotLookupAction(Request $request): Response
    {
        $sampleIdForm = $this->createForm(SampleLookupType::class, null);
        $sampleIdForm->handleRequest($request);

        if ($sampleIdForm->isSubmitted() && $sampleIdForm->isValid()) {
            $id = $sampleIdForm->get('sampleId')->getData();

            $sample = $this->em->getRepository(NphSample::class)->findOneBy([
                'sampleId' => $id
            ]);
            if ($sample) {
                return $this->redirectToRoute('nph_sample_finalize', [
                    'participantId' => $sample->getNphOrder()->getParticipantId(),
                    'orderId' => $sample->getNphOrder()->getId(),
                    'sampleId' => $sample->getId()
                ]);
            }
            $this->addFlash('error', 'Order ID not found');
        }

        return $this->render('program/nph/order/sample-aliquot-lookup.html.twig', [
            'sampleIdForm' => $sampleIdForm->createView()
        ]);
    }

    /**
     * @Route("/participant/{participantId}/order/{orderId}/sample/{sampleId}/finalize", name="nph_sample_finalize")
     */
    public function sampleFinalizeAction(
        $participantId,
        $orderId,
        $sampleId,
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
        $sample = $this->em->getRepository(NphSample::class)->findOneBy($sampleId);
        if (empty($sample)) {
            throw $this->createNotFoundException('Sample not found.');
        }
        $nphOrderService->loadModules($order->getModule(), $order->getVisitType(), $participantId);
        $sampleIdForm = $this->createForm(SampleLookupType::class, null);
        return $this->render('program/nph/order/sample-finalize.html.twig', [
            'sampleIdForm' => $sampleIdForm->createView(),
            'sample' => $sample,
            'participant' => $participant,
            'timePoints' => $nphOrderService->getTimePoints(),
            'samples' => $nphOrderService->getSamples(),
        ]);
    }
}
