<?php

namespace App\Controller;

use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Form\Nph\NphOrderCollect;
use App\Form\Nph\NphOrderType;
use App\Form\Nph\NphSampleFinalizeType;
use App\Form\Nph\NphSampleLookupType;
use App\Nph\Order\Samples;
use App\Service\Nph\NphOrderService;
use App\Service\ParticipantSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
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
            return $this->redirectToRoute('nph_order_label_print', ['participantId' => $participantId, 'module' => $module,
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
            NphOrderCollect::class,
            $orderCollectionData,
            ['samples' => $sampleLabels, 'orderType' => $order->getOrderType(), 'timeZone' => $this->getSecurityUser()->getTimezone()]
        );
        $oderCollectForm->handleRequest($request);
        if ($oderCollectForm->isSubmitted()) {
            $formData = $oderCollectForm->getData();
            if ($nphOrderService->isAtLeastOneSampleChecked($formData, $order) === false) {
                $oderCollectForm['samplesCheckAll']->addError(new FormError('Please select at least one sample'));
            }
            if ($oderCollectForm->isValid()) {
                if ($nphOrderService->saveOrderCollection($formData, $order)) {
                    $this->addFlash('success', 'Order collection saved');
                } else {
                    $this->addFlash('error', 'Order collection failed');
                }
            } else {
                $oderCollectForm->addError(new FormError('Please correct the errors below'));
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
        $sampleIdForm = $this->createForm(NphSampleLookupType::class, null);
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
            $this->addFlash('error', 'Sample ID not found');
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
        $sample = $this->em->getRepository(NphSample::class)->findOneBy([
            'nphOrder' => $order, 'id' => $sampleId
        ]);
        if (empty($sample)) {
            throw $this->createNotFoundException('Sample not found.');
        }
        $nphOrderService->loadModules($order->getModule(), $order->getVisitType(), $participantId);
        $sampleIdForm = $this->createForm(NphSampleLookupType::class, null);
        $sampleCode = $sample->getSampleCode();
        $sampleData = $nphOrderService->getExistingSampleData($sample);
        $sampleFinalizeForm = $this->createForm(
            NphSampleFinalizeType::class,
            $sampleData,
            ['sample' => $sampleCode, 'orderType' => $order->getOrderType(), 'timeZone' => $this->getSecurityUser()
                ->getTimezone(), 'aliquots' => $nphOrderService->getAliquots($sampleCode), 'disabled' => (bool)$sample->getFinalizedTs()]
        );
        $sampleFinalizeForm->handleRequest($request);
        if ($sampleFinalizeForm->isSubmitted()) {
            $formData = $sampleData = $sampleFinalizeForm->getData();
            if ($nphOrderService->hasAtLeastOneAliquotSample($formData, $sampleCode) === false) {
                $sampleFinalizeForm->addError(new FormError('Please select at least one sample'));
            }
            if ($sampleFinalizeForm->isValid()) {
                if ($nphOrderService->saveOrderFinalization($formData, $sample)) {
                    $this->addFlash('success', 'Order finalized');
                    return $this->redirectToRoute('nph_sample_finalize', [
                        'participantId' => $participantId,
                        'orderId' => $orderId,
                        'sampleId' => $sampleId
                    ]);
                } else {
                    $this->addFlash('error', 'Failed finalizing order');
                }
            } else {
                $sampleFinalizeForm->addError(new FormError('Please correct the errors below'));
            }
        }
        return $this->render('program/nph/order/sample-finalize.html.twig', [
            'sampleIdForm' => $sampleIdForm->createView(),
            'sampleFinalizeForm' => $sampleFinalizeForm->createView(),
            'sample' => $sample,
            'participant' => $participant,
            'timePoints' => $nphOrderService->getTimePoints(),
            'samples' => $nphOrderService->getSamples(),
            'aliquots' => $nphOrderService->getAliquots($sampleCode),
            'sampleData' => $sampleData
        ]);
    }

    /**
     * @Route("/participant/{participantId}/order/module/{module}/visit/{visit}/LabelPrint", name="nph_order_label_print")
     */
    public function orderSummary($participantId, $module, $visit, ParticipantSummaryService $participantSummaryService, NphOrderService $nphOrderService): Response
    {
        $participant = $participantSummaryService->getParticipantById($participantId);
        $nphOrderService->loadModules($module, $visit, $participantId);
        $orderInfo = $nphOrderService->getParticipantOrderSummaryByModuleAndVisit($participantId, $module, $visit);
        return $this->render(
            'program/nph/order/label-print.html.twig',
            ['participant' => $participant,
             'orderSummary' => $orderInfo['order'],
                'module' => $module,
                'visit' => $visit,
                'sampleCount' => $orderInfo['sampleCount']]
        );
    }
}
