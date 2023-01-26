<?php

namespace App\Controller;

use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Form\Nph\NphOrderCollect;
use App\Form\Nph\NphOrderType;
use App\Form\Nph\NphSampleFinalizeType;
use App\Form\Nph\NphSampleLookupType;
use App\Form\Nph\NphSampleModifyType;
use App\Form\Nph\NphSampleRdrRetryType;
use App\Service\EnvironmentService;
use App\Service\Nph\NphOrderService;
use App\Service\Nph\NphParticipantSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Util\Json;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        NphParticipantSummaryService $nphParticipantSummaryService,
        Request $request
    ): Response {
        $participant = $nphParticipantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $nphOrderService->loadModules($module, $visit, $participantId, $participant->biobankId);
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
            $sampleGroup = $nphOrderService->createOrdersAndSamples($formData);
            $this->addFlash('success', 'Orders Created');
            return $this->redirectToRoute('nph_order_label_print', ['participantId' => $participantId, 'module' => $module,
                'visit' => $visit, 'sampleGroup' => $sampleGroup]);
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
            'samplesOrderIds' => $nphOrderService->getSamplesWithOrderIds(),
            'samplesStatus' => $nphOrderService->getSamplesWithStatus()
        ]);
    }

    /**
     * @Route("/participant/{participantId}/order/{orderId}/collect", name="nph_order_collect")
     */
    public function orderCollectAction(
        $participantId,
        $orderId,
        NphOrderService $nphOrderService,
        NphParticipantSummaryService $nphNphParticipantSummaryService,
        Request $request
    ): Response {
        $participant = $nphNphParticipantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $order = $this->em->getRepository(NphOrder::class)->find($orderId);
        if (empty($order)) {
            throw $this->createNotFoundException('Order not found.');
        }
        $nphOrderService->loadModules($order->getModule(), $order->getVisitType(), $participantId, $participant->biobankId);
        $sampleLabelsIds = $nphOrderService->getSamplesWithLabelsAndIds($order->getNphSamples());
        $orderCollectionData = $nphOrderService->getExistingOrderCollectionData($order);
        $oderCollectForm = $this->createForm(
            NphOrderCollect::class,
            $orderCollectionData,
            ['samples' => $sampleLabelsIds, 'orderType' => $order->getOrderType(), 'timeZone' =>
                $this->getSecurityUser()->getTimezone()]
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
        NphParticipantSummaryService $nphNphParticipantSummaryService,
        Request $request
    ): Response {
        $participant = $nphNphParticipantSummaryService->getParticipantById($participantId);
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
        $nphOrderService->loadModules(
            $order->getModule(),
            $order->getVisitType(),
            $participantId,
            $participant->biobankId
        );
        $sampleIdForm = $this->createForm(NphSampleLookupType::class, null);
        $sampleCode = $sample->getSampleCode();
        $sampleData = $nphOrderService->getExistingSampleData($sample);
        $sampleFinalizeForm = $this->createForm(
            NphSampleFinalizeType::class,
            $sampleData,
            ['sample' => $sampleCode, 'orderType' => $order->getOrderType(), 'timeZone' => $this->getSecurityUser()
                ->getTimezone(), 'aliquots' => $nphOrderService->getAliquots($sampleCode), 'disabled' =>
                $sample->isDisabled(), 'nphSample' => $sample
            ]
        );
        $sampleFinalizeForm->handleRequest($request);
        if ($sampleFinalizeForm->isSubmitted()) {
            if ($sample->isDisabled()) {
                throw $this->createAccessDeniedException();
            }
            $formData = $sampleData = $sampleFinalizeForm->getData();
            if (!empty($nphOrderService->getAliquots($sampleCode))) {
                if ($sample->getModifyType() !== NphSample::UNLOCK && $nphOrderService->hasAtLeastOneAliquotSample(
                    $formData,
                    $sampleCode
                ) === false) {
                    $sampleFinalizeForm['aliquotError']->addError(new FormError('Please enter at least one aliquot'));
                } elseif ($duplicate = $nphOrderService->checkDuplicateAliquotId($formData, $sampleCode)) {
                    $sampleFinalizeForm[$duplicate['aliquotCode']][$duplicate['key']]->addError(new FormError('Aliquot ID already exists'));
                }
            }
            if ($sampleFinalizeForm->isValid()) {
                if ($nphOrderService->saveOrderFinalization($formData, $sample)) {
                    $this->addFlash('success', 'Sample finalized');
                    return $this->redirectToRoute('nph_sample_finalize', [
                        'participantId' => $participantId,
                        'orderId' => $orderId,
                        'sampleId' => $sampleId
                    ]);
                } else {
                    $this->addFlash('error', 'Failed finalizing sample. Please try again.');
                }
            } else {
                $sampleFinalizeForm->addError(new FormError('Please correct the errors below'));
            }
        }

        if ($request->query->has('modifyType')) {
            $modifyType = $request->query->get('modifyType');
            if ($modifyType !== NphSample::UNLOCK || $sample->canUnlock() === false) {
                throw $this->createNotFoundException();
            }
            if ($modifyType === $sample->getModifyType()) {
                throw $this->createNotFoundException();
            }
            $nphSampleModifyForm = $this->createForm(NphSampleModifyType::class, null, ['type' => $modifyType]);
            $nphSampleModifyForm->handleRequest($request);
            if ($nphSampleModifyForm->isSubmitted()) {
                $sampleModifyData = $nphSampleModifyForm->getData();
                if ($nphSampleModifyForm->isValid()) {
                    $nphOrderService->saveSampleModification($sampleModifyData, NphSample::UNLOCK, $sample);
                    $successText = $sample::$modifySuccessText;
                    $this->addFlash('success', "Sample {$successText[$modifyType]}");
                    return $this->redirectToRoute('nph_sample_finalize', [
                        'participantId' => $participantId,
                        'orderId' => $orderId,
                        'sampleId' => $sampleId
                    ]);
                } else {
                    $nphSampleModifyForm->addError(new FormError('Please correct the errors below'));
                }
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
            'sampleData' => $sampleData,
            'sampleModifyForm' => isset($nphSampleModifyForm) ? $nphSampleModifyForm->createView() : '',
            'modifyType' => $modifyType ?? ''
        ]);
    }

    /**
     * @Route("/participant/{participantId}/order/module/{module}/visit/{visit}/LabelPrint/{sampleGroup}", name="nph_order_label_print")
     */
    public function orderSummary($participantId, $module, $visit, $sampleGroup, NphParticipantSummaryService
    $nphNphParticipantSummaryService, NphOrderService $nphOrderService): Response
    {
        $participant = $nphNphParticipantSummaryService->getParticipantById($participantId);
        $nphOrderService->loadModules($module, $visit, $participantId, $participant->biobankId);
        $orderInfo = $nphOrderService->getParticipantOrderSummaryByModuleVisitAndSampleGroup($participantId, $module, $visit, $sampleGroup);
        return $this->render(
            'program/nph/order/label-print.html.twig',
            ['participant' => $participant,
             'orderSummary' => $orderInfo['order'],
                'module' => $module,
                'visit' => $visit,
                'sampleCount' => $orderInfo['sampleCount'],
                'sampleGroup' => $sampleGroup]
        );
    }

    /**
     * @Route("/participant/{participantId}/order/{orderId}/samples/modify/{type}", name="nph_samples_modify")
     */
    public function sampleModifyAction(
        $participantId,
        string $orderId,
        string $type,
        NphOrderService $nphOrderService,
        NphParticipantSummaryService $nphNphParticipantSummaryService,
        Request $request
    ): Response {
        if (!in_array($type, [NphSample::CANCEL, NphSample::RESTORE, NphSample::UNLOCK])) {
            throw $this->createNotFoundException();
        }
        $participant = $nphNphParticipantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $order = $this->em->getRepository(NphOrder::class)->find($orderId);
        if (empty($order)) {
            throw $this->createNotFoundException('Order not found.');
        }
        if ($order->canModify($type) === false) {
            throw $this->createNotFoundException();
        }
        $nphOrderService->loadModules($order->getModule(), $order->getVisitType(), $participantId, $participant->biobankId);
        $nphSampleModifyForm = $this->createForm(NphSampleModifyType::class, null, [
            'type' => $type, 'samples' => $order->getNphSamples()
        ]);
        $nphSampleModifyForm->handleRequest($request);
        if ($nphSampleModifyForm->isSubmitted()) {
            $samplesModifyData = $nphSampleModifyForm->getData();
            if ($nphOrderService->isAtLeastOneSampleChecked($samplesModifyData, $order) === false) {
                $nphSampleModifyForm['samplesCheckAll']->addError(new FormError('Please select at least one sample'));
            }
            if ($nphSampleModifyForm->isValid()) {
                if ($nphOrderService->saveSamplesModification($samplesModifyData, $type, $order)) {
                    $modifySuccessText = NphSample::$modifySuccessText[$type];
                    $this->addFlash('success', "Samples {$modifySuccessText}");
                    return $this->redirectToRoute('nph_order_collect', [
                        'participantId' => $participantId,
                        'orderId' => $orderId
                    ]);
                } else {
                    $this->addFlash('error', "Failed to {$type} one or more samples. Please try again.");
                    return $this->redirectToRoute('nph_samples_modify', [
                        'participantId' => $participantId,
                        'orderId' => $orderId,
                        'type' => $type
                    ]);
                }
            } else {
                $nphSampleModifyForm->addError(new FormError('Please correct the errors below'));
            }
        }
        return $this->render('program/nph/order/sample-modify.html.twig', [
            'participant' => $participant,
            'order' => $order,
            'sampleModifyForm' => $nphSampleModifyForm->createView(),
            'type' => $type,
            'timePoints' => $nphOrderService->getTimePoints(),
            'samples' => $nphOrderService->getSamples(),
            'samplesMetadata' => $nphOrderService->getSamplesMetadata($order)
        ]);
    }

    /**
     * @Route("/participant/{participantId}/order/{orderId}/sample/{sampleId}/json-response", name="nph_order_json")
     * For debugging generated JSON representation - only allowed for admins or in local dev
     */
    public function nphOrderJsonAction(
        string $participantId,
        string $orderId,
        string $sampleId,
        NphParticipantSummaryService $nphParticipantSummaryService,
        NphOrderService $nphOrderService,
        EnvironmentService $env
    ): Response {
        $participant = $nphParticipantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        if (!$this->isGranted('ROLE_ADMIN') && !$env->isLocal()) {
            throw $this->createAccessDeniedException();
        }
        $order = $this->em->getRepository(NphOrder::class)->find($orderId);
        $sample = $this->em->getRepository(NphSample::class)->findOneBy([
            'nphOrder' => $order, 'id' => $sampleId
        ]);
        $nphOrderService->loadModules($order->getModule(), $order->getVisitType(), $participantId, $participant->biobankId);
        $object = $nphOrderService->getRdrObject($order, $sample);
        $response = new JsonResponse($object);
        $response->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_PRETTY_PRINT);
        return $response;
    }
}
