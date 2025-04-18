<?php

namespace App\Controller;

use App\Audit\Log;
use App\Entity\NphAliquot;
use App\Entity\NphDlw;
use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Form\DlwType;
use App\Form\Nph\NphOrderCollect;
use App\Form\Nph\NphOrderType;
use App\Form\Nph\NphSampleFinalizeType;
use App\Form\Nph\NphSampleLookupType;
use App\Form\Nph\NphSampleModifyBulkType;
use App\Form\Nph\NphSampleModifyType;
use App\Form\Nph\NphSampleRevertType;
use App\HttpClient;
use App\Nph\Order\Samples;
use App\Service\EnvironmentService;
use App\Service\HelpService;
use App\Service\LoggerService;
use App\Service\Nph\NphOrderService;
use App\Service\Nph\NphParticipantSummaryService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route(path: '/nph')]
class NphOrderController extends BaseController
{
    private SiteService $siteService;

    public function __construct(EntityManagerInterface $em, SiteService $siteService)
    {
        parent::__construct($em);
        $this->siteService = $siteService;
    }

    #[Route(path: '/participant/{participantId}/order/module/{module}/visit/{visit}', name: 'nph_generate_oder')]
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
        if (!$participant->dob) {
            throw $this->createAccessDeniedException('DOB has not been provided. The participant must complete “The Basics” survey that captures their DOB to unlock order generation.');
        }
        $this->checkCrossSiteParticipant($participant->nphPairedSiteSuffix);
        $nphOrderService->loadModules($module, $visit, $participantId, $participant->biobankId);
        $dietPeriod = $module === 1 ? $visit : substr($visit, 0, 7);
        if (!$nphOrderService->canGenerateOrders($participantId, $module, $dietPeriod, $participant->module)) {
            throw $this->createNotFoundException('Orders cannot be generated for this diet.');
        }
        $timePointSamples = $nphOrderService->getTimePointSamples();
        $timePoints = $nphOrderService->getTimePoints();
        $ordersData = $nphOrderService->getExistingOrdersData();
        $stoolSamples = array_merge($nphOrderService->getSamplesByType('stool'), $nphOrderService->getSamplesByType('stool2'));
        $oderForm = $this->createForm(
            NphOrderType::class,
            $ordersData,
            ['timePointSamples' => $timePointSamples, 'timePoints' => $timePoints, 'stoolSamples' =>
                $stoolSamples,
                'module1tissueCollectConsent' => $participant->module1TissueConsentStatus,
                'module' => $module, 'userTimezone' => $this->getSecurityUser()->getTimezone()]
        );
        $showPreview = false;
        $oderForm->handleRequest($request);
        if ($oderForm->isSubmitted()) {
            $formData = $oderForm->getData();
            if ($formErrors = $nphOrderService->validateGenerateOrdersData($formData)) {
                foreach ($formErrors as $formError) {
                    $oderForm[$formError['field']]->addError(new FormError($formError['message']));
                }
            }
            if ($oderForm->isValid()) {
                /** @var SubmitButton $validateButton */
                $validateButton = $oderForm->get('validate');
                if ($validateButton->isClicked()) {
                    $showPreview = true;
                } else {
                    $sampleGroup = $nphOrderService->createOrdersAndSamples($formData);
                    $this->addFlash('success', 'Orders Created');
                    return $this->redirectToRoute('nph_order_label_print', ['participantId' => $participantId, 'module' => $module,
                        'visit' => $visit, 'sampleGroup' => $sampleGroup]);
                }
            } else {
                $oderForm->addError(new FormError('Please correct the errors below'));
            }
        }
        $downtimeOrders = $nphOrderService->getDowntimeOrderSummary();
        return $this->render('program/nph/order/generate-orders.html.twig', [
            'orderForm' => $oderForm->createView(),
            'timePointSamples' => $timePointSamples,
            'participant' => $participant,
            'module' => $module,
            'visit' => $visit,
            'downtimeOrders' => $downtimeOrders,
            'visitDisplayName' => NphOrder::VISIT_DISPLAY_NAME_MAPPER[$visit],
            'timePoints' => $nphOrderService->getTimePoints(),
            'samples' => $nphOrderService->getSamples(),
            'stoolSamples' => $stoolSamples,
            'nailSamples' => $nphOrderService->getSamplesByType(NphOrder::TYPE_NAIL),
            'bloodSamples' => $nphOrderService->getSamplesByType(NphOrder::TYPE_BLOOD),
            'samplesOrderIds' => $nphOrderService->getSamplesWithOrderIds(),
            'samplesStatus' => $nphOrderService->getSamplesWithStatus(),
            'showPreview' => $showPreview,
            'downtimeVideoSrc' => HelpService::$nphVideoPlaylists['downtime-reference']
        ]);
    }

    #[Route(path: '/participant/{participantId}/order/{orderId}/collect', name: 'nph_order_collect')]
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
        $this->checkCrossSiteParticipant($participant->nphPairedSiteSuffix);
        $order = $this->em->getRepository(NphOrder::class)->find($orderId);
        if (empty($order)) {
            throw $this->createNotFoundException('Order not found.');
        }
        $nphOrderService->loadModules($order->getModule(), $order->getVisitPeriod(), $participantId, $participant->biobankId);
        $sampleLabelsIds = $nphOrderService->getSamplesWithLabelsAndIds($order->getNphSamples());
        $orderCollectionData = $nphOrderService->getExistingOrderCollectionData($order);
        $oderCollectForm = $this->createForm(
            NphOrderCollect::class,
            $orderCollectionData,
            ['samples' => $sampleLabelsIds, 'orderType' => $order->getOrderType(), 'timeZone' =>
                $this->getSecurityUser()->getTimezone(), 'disableMetadataFields' => $order->isMetadataFieldDisabled()
                , 'disableStoolCollectedTs' => $order->isStoolCollectedTsDisabled(), 'orderCreatedTs' => $order->getCreatedTs()]
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
        $activeSamples = $this->em->getRepository(NphSample::class)->findActiveSampleCodes($order, $this->siteService->getSiteId());
        return $this->render('program/nph/order/collect.html.twig', [
            'order' => $order,
            'activeSamples' => $activeSamples,
            'orderCollectForm' => $oderCollectForm->createView(),
            'participant' => $participant,
            'timePoints' => $nphOrderService->getTimePoints(),
            'samples' => $nphOrderService->getSamples(),
        ]);
    }

    #[Route(path: '/samples/aliquot', name: 'nph_samples_aliquot')]
    public function sampleAliquotLookupAction(
        Request $request,
        NphParticipantSummaryService $nphParticipantSummaryService
    ): Response {
        $sampleIdForm = $this->createForm(NphSampleLookupType::class, null);
        $sampleIdForm->handleRequest($request);

        if ($sampleIdForm->isSubmitted() && $sampleIdForm->isValid()) {
            $id = $sampleIdForm->get('sampleId')->getData();

            $sample = $this->em->getRepository(NphSample::class)->findOneBy([
                'sampleId' => $id
            ]);
            if ($sample) {
                $participantId = $sample->getNphOrder()->getParticipantId();
                $participant = $nphParticipantSummaryService->getParticipantById($participantId);
                if (!$participant) {
                    throw $this->createNotFoundException('Participant not found.');
                }
                if ($participant->nphPairedSiteSuffix === $this->siteService->getSiteId()) {
                    return $this->redirectToRoute('nph_sample_finalize', [
                        'participantId' => $sample->getNphOrder()->getParticipantId(),
                        'orderId' => $sample->getNphOrder()->getId(),
                        'sampleId' => $sample->getId()
                    ]);
                }
                $crossSiteErrorMessage = 'Lookup for this sample ID is not permitted because the participant is paired with another site';
            }
            $this->addFlash('error', $crossSiteErrorMessage ?? 'Sample ID not found');
        }

        return $this->render('program/nph/order/sample-aliquot-lookup.html.twig', [
            'sampleIdForm' => $sampleIdForm->createView(),
            'biobankView' => false
        ]);
    }

    #[Route(path: '/participant/{participantId}/order/{orderId}/sample/{sampleId}/finalize', name: 'nph_sample_finalize')]
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
        $this->checkCrossSiteParticipant($participant->nphPairedSiteSuffix);
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
            $order->getVisitPeriod(),
            $participantId,
            $participant->biobankId
        );
        $sampleIdForm = $this->createForm(NphSampleLookupType::class, null);
        $sampleCode = $sample->getSampleCode();
        $sampleData = $nphOrderService->getExistingSampleData($sample);
        $dietPeriod = $order->getModule() === 1 ? $order->getVisitPeriod() : substr($order->getVisitPeriod(), 0, 7);
        $canGenerateOrders = $nphOrderService->canGenerateOrders($participantId, $order->getModule(), $dietPeriod, $participant->module);
        $isFormDisabled = $sample->isDisabled() || ($sample->getModifyType() !== NphSample::UNLOCK && !$canGenerateOrders);
        $isFreezeTsDisabled = $order->getOrderType() === NphOrder::TYPE_STOOL ? $order->isFreezeTsDisabled($sample->getModifyType()) : false;
        $sampleFinalizeForm = $this->createForm(
            NphSampleFinalizeType::class,
            $sampleData,
            ['sample' => $sampleCode, 'orderType' => $order->getOrderType(), 'timeZone' => $this->getSecurityUser()
                ->getTimezone(), 'aliquots' => $nphOrderService->getAliquots($sampleCode), 'disabled' =>
                $isFormDisabled, 'nphSample' => $sample, 'disableMetadataFields' =>
                $order->isMetadataFieldDisabled(), 'disableStoolCollectedTs' => $sample->getModifyType() !== NphSample::UNLOCK &&
                $order->isStoolCollectedTsDisabled(), 'orderCreatedTs' => $order->getCreatedTs(),
                'module' => $order->getModule(), 'disableFreezeTs' => $isFreezeTsDisabled
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
                } elseif ($nphOrderService->hasDuplicateAliquotsInForm($formData, $sampleCode)) {
                    $sampleFinalizeForm['aliquotError']->addError(new FormError('Please enter a unique aliquot barcode'));
                } else {
                    $duplicate = $nphOrderService->checkDuplicateAliquotId($formData, $sampleCode, $sample->getNphAliquotIds());
                    if ($duplicate) {
                        $sampleFinalizeForm[$duplicate['aliquotCode']][$duplicate['key']]->addError(new FormError('Aliquot ID already exists'));
                    }
                }
            }
            if ($sampleFinalizeForm->isValid()) {
                if ($nphOrderService->saveFinalization($formData, $sample)) {
                    $this->addFlash('success', 'Sample finalized');
                    return $this->redirectToRoute('nph_sample_finalize', [
                        'participantId' => $participantId,
                        'orderId' => $orderId,
                        'sampleId' => $sampleId
                    ]);
                }
                $this->addFlash('error', 'Failed finalizing sample. Please try again.');
                $this->em->refresh($sample);
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
                }
                $nphSampleModifyForm->addError(new FormError('Please correct the errors below'));
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
            'modifyType' => $modifyType ?? '',
            'revertForm' => $this->createForm(NphSampleRevertType::class)->createView(),
            'biobankView' => false,
            'isFormDisabled' => $isFormDisabled,
            'visitDiet' => $nphOrderService->getVisitDiet(),
            'order' => $order,
            'isFreezeTsDisabled' => $isFreezeTsDisabled,
            'allowResubmit' => false
        ]);
    }

    #[Route(path: '/participant/{participantId}/order/module/{module}/visit/{visit}/LabelPrint/{sampleGroup}', name: 'nph_order_label_print')]
    public function orderSummary($participantId, $module, $visit, $sampleGroup, NphParticipantSummaryService $nphNphParticipantSummaryService, NphOrderService $nphOrderService): Response
    {
        $participant = $nphNphParticipantSummaryService->getParticipantById($participantId);
        $this->checkCrossSiteParticipant($participant->nphPairedSiteSuffix);
        $nphOrderService->loadModules($module, $visit, $participantId, $participant->biobankId);
        $orderInfo = $nphOrderService->getParticipantOrderSummaryByModuleVisitAndSampleGroup($participantId, $module, $visit, $sampleGroup);
        return $this->render(
            'program/nph/order/label-print.html.twig',
            ['participant' => $participant,
                'orderSummary' => $orderInfo['order'],
                'module' => $module,
                'visit' => $visit,
                'visitDisplayName' => NphOrder::VISIT_DISPLAY_NAME_MAPPER[$visit],
                'sampleCount' => $orderInfo['sampleCount'],
                'sampleGroup' => $sampleGroup]
        );
    }

    #[Route(path: '/participant/{participantId}/module/{module}/samples/modify/{type}', name: 'nph_samples_modify_all')]
    public function sampleModifyAll(
        string $participantId,
        string $module,
        string $type,
        NphOrderService $nphOrderService,
        NphParticipantSummaryService $nphNphParticipantSummaryService,
        Request $request
    ) {
        if (!in_array($type, [NphSample::CANCEL, NphSample::RESTORE, NphSample::UNLOCK])) {
            throw $this->createNotFoundException();
        }
        $participant = $nphNphParticipantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $this->checkCrossSiteParticipant($participant->nphPairedSiteSuffix);
        $activeSamples = $this->em->getRepository(NphSample::class)->findActiveSamplesByParticipantId($participantId, $module);
        $nphSampleModifyForm = $this->createForm(NphSampleModifyBulkType::class, null, [
            'type' => $type, 'samples' => $activeSamples, 'activeSamples' => $activeSamples,
        ]);
        $nphSampleModifyForm->handleRequest($request);
        if ($nphSampleModifyForm->isSubmitted()) {
            $samplesModifyData = $nphSampleModifyForm->getData();
            $isAtleastOneSampleChecked = false;
            foreach ($samplesModifyData as $checked) {
                if ($checked === true) {
                    $isAtleastOneSampleChecked = true;
                    break;
                }
            }
            if (!$isAtleastOneSampleChecked) {
                $nphSampleModifyForm['samplesCheckAll']->addError(new FormError('Please select at least one sample'));
            }
            if ($nphSampleModifyForm->isValid()) {
                if ($nphOrderService->updateSampleModificationBulk($samplesModifyData, $type)) {
                    $modifySuccessText = NphSample::$modifySuccessText[$type];
                    $this->addFlash('success', "Samples {$modifySuccessText}");
                    return $this->redirectToRoute('nph_participant_summary', [
                        'participantId' => $participantId,
                    ]);
                }
                $this->addFlash('error', "Failed to {$type} one or more samples. Please try again.");
                return $this->redirectToRoute('nph_samples_modify_all', [
                    'participantId' => $participantId,
                    'module' => $module,
                    'type' => $type
                ]);
            }
            $nphSampleModifyForm->addError(new FormError('Please correct the errors below'));
        }
        $orderSummary = $nphOrderService->getParticipantOrderSummary($participantId);
        return $this->render('program/nph/order/sample-modify-bulk.html.twig', [
            'activeSamples' => $activeSamples,
            'participant' => $participant,
            'sampleModifyForm' => $nphSampleModifyForm->createView(),
            'ordersSummary' => $orderSummary['order'][$module],
            'module' => $module,
            'type' => $type,
        ]);
    }
    #[Route(path: '/participant/{participantId}/order/{orderId}/samples/modify/{type}', name: 'nph_samples_modify')]
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
        $this->checkCrossSiteParticipant($participant->nphPairedSiteSuffix);
        $order = $this->em->getRepository(NphOrder::class)->find($orderId);
        if (empty($order)) {
            throw $this->createNotFoundException('Order not found.');
        }
        if ($order->canModify($type) === false) {
            throw $this->createNotFoundException();
        }
        $activeSamples = $this->em->getRepository(NphSample::class)->findActiveSampleCodes($order, $this->siteService->getSiteId());
        $nphOrderService->loadModules($order->getModule(), $order->getVisitPeriod(), $participantId, $participant->biobankId);
        $nphSampleModifyForm = $this->createForm(NphSampleModifyType::class, null, [
            'type' => $type, 'samples' => $order->getNphSamples(), 'activeSamples' => $activeSamples
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
                }
                $this->addFlash('error', "Failed to {$type} one or more samples. Please try again.");
                return $this->redirectToRoute('nph_samples_modify', [
                    'participantId' => $participantId,
                    'orderId' => $orderId,
                    'type' => $type
                ]);
            }
            $nphSampleModifyForm->addError(new FormError('Please correct the errors below'));
        }
        return $this->render('program/nph/order/sample-modify.html.twig', [
            'activeSamples' => $activeSamples,
            'participant' => $participant,
            'order' => $order,
            'sampleModifyForm' => $nphSampleModifyForm->createView(),
            'type' => $type,
            'timePoints' => $nphOrderService->getTimePoints(),
            'samples' => $nphOrderService->getSamples(),
            'samplesMetadata' => $nphOrderService->getSamplesMetadata($order)
        ]);
    }

    #[Route(path: '/participant/{participantId}/order/{orderId}/sample/{sampleId}/json-response', name: 'nph_order_json')]
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
        if (!$this->isGranted('ROLE_NPH_ADMIN') && !$env->isLocal()) {
            throw $this->createAccessDeniedException();
        }
        $order = $this->em->getRepository(NphOrder::class)->find($orderId);
        $sample = $this->em->getRepository(NphSample::class)->findOneBy([
            'nphOrder' => $order, 'id' => $sampleId
        ]);
        $nphOrderService->loadModules($order->getModule(), $order->getVisitPeriod(), $participantId, $participant->biobankId);
        $object = $nphOrderService->getRdrObject($order, $sample);
        $response = new JsonResponse($object);
        $response->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_PRETTY_PRINT);
        return $response;
    }

    #[Route(path: '/participant/{participantId}/order/{orderId}/sample/{sampleId}/revert', name: 'nph_sample_revert', methods: ['POST'])]
    public function sampleRevertAction(
        string $participantId,
        string $orderId,
        string $sampleId,
        Request $request,
        NphParticipantSummaryService $nphParticipantSummaryService,
        NphOrderService $nphOrderService,
        LoggerService $loggerService
    ): Response {
        $participant = $nphParticipantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $this->checkCrossSiteParticipant($participant->nphPairedSiteSuffix);
        $sample = $this->em->getRepository(NphSample::class)->findOneBy([
            'id' => $sampleId
        ]);
        if (empty($sample)) {
            throw $this->createNotFoundException('Sample not found.');
        }
        if ($sample->getModifyType() !== NphSample::UNLOCK) {
            throw $this->createAccessDeniedException();
        }
        $order = $this->em->getRepository(NphOrder::class)->find($orderId);
        $nphOrderService->loadModules(
            $order->getModule(),
            $order->getVisitPeriod(),
            $participantId,
            $participant->biobankId
        );
        $sampleRevertForm = $this->createForm(NphSampleRevertType::class, null);
        $sampleRevertForm->handleRequest($request);
        if ($sampleRevertForm->isSubmitted() && $sampleRevertForm->isValid()) {
            try {
                $sample->setModifyType(NphSample::REVERT);
                $this->em->persist($sample);
                $this->em->flush();
                $loggerService->log(Log::NPH_SAMPLE_UPDATE, $sample->getId());
                $this->addFlash('success', 'Sample reverted');
            } catch (\Exception $e) {
                $loggerService->log('error', $e->getMessage());
                $this->addFlash('error', 'Sample revert failed. Please try again.');
            }
        }
        return $this->redirectToRoute('nph_sample_finalize', [
            'participantId' => $participantId,
            'orderId' => $orderId,
            'sampleId' => $sampleId
        ]);
    }

    #[Route(path: '/aliquot/instructions/file/{id}', name: 'aliquot_instructions_file')]
    public function aliquotInstructions($id, HelpService $helpService)
    {
        $document = Samples::$aliquotDocuments[$id] ?? null;
        if (!$document) {
            throw $this->createNotFoundException('Page Not Found!');
        }
        $documentFile = $document['filename'];
        $url = $helpService->getStoragePath() . '/' . rawurlencode($documentFile);
        try {
            $client = new HttpClient();
            $response = $client->get($url, ['stream' => true]);
            $responseBody = $response->getBody();
            $streamedResponse = new StreamedResponse(function () use ($responseBody) {
                while (!$responseBody->eof()) {
                    echo $responseBody->read(1024); // phpcs:ignore WordPress.XSS.EscapeOutput
                }
            });
            $streamedResponse->headers->set('Content-Type', 'application/pdf');
            return $streamedResponse;
        } catch (\Exception $e) {
            error_log('Failed to retrieve Confluence file ' . $url . ' (' . $id . ')');
            echo '<html><body style="font-family: Helvetica Neue,Helvetica,Arial,sans-serif"><strong>File could not be loaded</strong></body></html>';
            exit;
        }
    }

    #[Route(path: '/participant/{participantId}/module/{module}/visit/{visit}/dlw/collect', name: 'nph_dlw_collect')]
    public function dlwSampleCollect(
        $participantId,
        $module,
        $visit,
        NphOrderService $nphOrderService,
        NphParticipantSummaryService $nphNphParticipantSummaryService,
        Request $request
    ): Response {
        $participant = $nphNphParticipantSummaryService->getParticipantById($participantId);
        $dlwObject = $this->em->getRepository(NphDlw::class)->findOneBy([
            'NphParticipant' => $participantId,
            'visitPeriod' => $visit,
            'module' => $module
        ]);
        $dlwForm = $this->createForm(DlwType::class, $dlwObject, ['timezone' => $this->getSecurityUser()->getTimezone()]);
        $dlwForm->handleRequest($request);
        if ($dlwForm->isSubmitted()) {
            if ($dlwForm->isValid()) {
                $errorThrown = false;
                try {
                    $dlwObject = $nphOrderService->saveDlwCollection($dlwForm->getData(), $participantId, $module, $visit);
                } catch (\Exception $e) {
                    $this->addFlash('error', $e->getMessage());
                    $errorThrown = true;
                }
                if ($errorThrown) {
                    $disabled = false;
                } else {
                    $disabled = true;
                }
            } else {
                $disabled = false;
            }
        } else {
            $disabled = (bool) $dlwObject;
        }
        return $this->render('program/nph/order/dlw-collect.html.twig', [
            'participant' => $participant,
            'module' => $module,
            'visit' => $visit,
            'visitDisplayName' => NphOrder::VISIT_DISPLAY_NAME_MAPPER[$visit],
            'disabled' => $disabled,
            'dlwInfo' => $dlwObject,
            'form' => $dlwForm->createView()
        ]);
    }

    #[Route(path: '/ajax/search/aliquot', name: 'search_aliquot_id')]
    public function aliquotIdSearchAction(Request $request): JsonResponse
    {
        $aliquotId = $request->get('aliquotId');
        $aliquot = $this->em->getRepository(NphAliquot::class)->findOneBy([
            'aliquotId' => $aliquotId
        ]);
        if ($aliquot) {
            return $this->json(['status' => false, 'type' => 'aliquot']);
        }
        $sample = $this->em->getRepository(NphSample::class)->findOneBy([
            'sampleId' => $aliquotId
        ]);
        if ($sample) {
            return $this->json(['status' => false, 'type' => 'sample']);
        }
        return $this->json(['status' => true]);
    }

    #[Route(path: '/ajax/search/stool', name: 'search_stool_id')]
    public function stoolSearchAction(Request $request): JsonResponse
    {
        $stoolId = $request->get('stoolId');
        $type = $request->get('type');
        if ($type === 'kit') {
            $stool = $this->em->getRepository(NphOrder::class)->findOneBy([
                'orderId' => $stoolId
            ]);
        } else {
            $stool = $this->em->getRepository(NphSample::class)->findOneBy([
                'sampleId' => $stoolId
            ]);
        }
        return $this->json(!$stool);
    }

    #[Route(path: '/ajax/save/notes', name: 'save_collection_notes')]
    public function aliquotSaveCollectionNotes(Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfToken || !$csrfTokenManager->isTokenValid(new CsrfToken('save_notes', $csrfToken))) {
            return $this->json(['status' => false, 'message' => 'Invalid CSRF token'], 403);
        }
        $sampleId = $request->get('sampleId');
        $sample = $this->em->getRepository(NphSample::class)->findOneBy([
            'sampleId' => $sampleId,
        ]);
        $collectedNotes = $request->get('notes');
        if (empty($sample) || empty($collectedNotes)) {
            return $this->json(['status' => false, 'message' => 'Invalid input']);
        }
        $sample->setCollectedNotes($collectedNotes);
        $this->em->persist($sample);
        $this->em->flush();
        return $this->json(['status' => true, 'message' => 'Notes saved successfully']);
    }

    private function checkCrossSiteParticipant(string $participantSiteId): void
    {
        if ($participantSiteId !== $this->siteService->getSiteId()) {
            throw $this->createNotFoundException('Page not available because this participant is paired with another site.');
        }
    }
}
