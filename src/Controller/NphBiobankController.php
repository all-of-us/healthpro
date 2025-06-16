<?php

namespace App\Controller;

use App\Audit\Log;
use App\Entity\NphAdminOrderEditLog;
use App\Entity\NphAliquot;
use App\Entity\NphGenerateOrderWarningLog;
use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Entity\NphSampleProcessingStatus;
use App\Form\Nph\NphSampleFinalizeType;
use App\Form\Nph\NphSampleLookupType;
use App\Form\Nph\NphSampleModifyType;
use App\Form\Nph\NphSampleResubmitType;
use App\Form\Nph\NphSampleRevertType;
use App\Form\OrderLookupIdType;
use App\Form\ParticipantLookupBiobankIdType;
use App\Form\ReviewTodayFilterType;
use App\Helper\NphDietPeriodStatus;
use App\Service\LoggerService;
use App\Service\Nph\NphOrderService;
use App\Service\Nph\NphParticipantReviewService;
use App\Service\Nph\NphParticipantSummaryService;
use App\Service\Nph\NphProgramSummaryService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/nph/biobank')]
class NphBiobankController extends BaseController
{
    protected NphParticipantSummaryService $nphParticipantSummaryService;
    protected ParameterBagInterface $params;
    protected NphParticipantReviewService $nphParticipantReviewService;

    public function __construct(
        EntityManagerInterface $em,
        NphParticipantSummaryService $nphParticipantSummaryService,
        ParameterBagInterface $params,
        NphParticipantReviewService $nphParticipantReviewService
    ) {
        parent::__construct($em);
        $this->nphParticipantSummaryService = $nphParticipantSummaryService;
        $this->params = $params;
        $this->nphParticipantReviewService = $nphParticipantReviewService;
    }

    #[Route(path: '/', name: 'nph_biobank_home')]
    public function indexAction(): Response
    {
        return $this->render('program/nph/biobank/index.html.twig');
    }

    #[Route(path: '/participants', name: 'nph_biobank_participants')]
    public function participantsAction(Request $request): Response
    {
        $bioBankIdPrefix = $this->params->has('nph_biobank_id_prefix') ? $this->params->get('nph_biobank_id_prefix') : null;
        $idForm = $this->createForm(ParticipantLookupBiobankIdType::class, null, ['bioBankIdPrefix' => $bioBankIdPrefix]);
        $idForm->handleRequest($request);
        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $searchParameters = $idForm->getData();
            $searchResults = $this->nphParticipantSummaryService->search($searchParameters);
            if (!empty($searchResults)) {
                return $this->redirectToRoute('nph_biobank_participant', [
                    'biobankId' => $searchResults[0]->biobankId
                ]);
            }
            $this->addFlash('error', 'Biobank ID not found');
        }
        return $this->render('program/nph/biobank/participants.html.twig', [
            'idForm' => $idForm->createView()
        ]);
    }

    #[Route(path: '/orderlookup', name: 'nph_biobank_order_lookup')]
    public function orderLookupAction(
        Request $request,
        NphParticipantSummaryService $participantSummary
    ): Response {
        $idForm = $this->createForm(OrderLookupIdType::class, null);
        $idForm->handleRequest($request);

        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $id = $idForm->get('orderId')->getData();

            $order = $this->em->getRepository(NphOrder::class)->findOneBy([
                'orderId' => $id
            ]);

            if ($order) {
                $participant = $participantSummary->getParticipantById($order->getParticipantId());
                return $this->redirectToRoute('nph_biobank_order_collect', [
                    'biobankId' => $participant->biobankId,
                    'orderId' => $order->getId()
                ]);
            }
            $this->addFlash('error', 'Order ID not found');
        }
        return $this->render(
            'program/nph/order/orderlookup.html.twig',
            [
                'idForm' => $idForm->createView(),
                'recentOrders' => null,
                'biobankView' => true,
            ]
        );
    }

    #[Route(path: '/review/orders/today', name: 'nph_biobank_orders_today')]
    public function ordersTodayAction(): Response
    {
        $samples = $this->em->getRepository(NphOrder::class)->getTodaysBiobankOrders($this->getSecurityUser()->getTimeZone());
        return $this->render('/program/nph/biobank/orders-today.html.twig', [
            'samples' => $samples
        ]);
    }
    #[Route(path: '/review/orders/audit', name: 'nph_biobank_orders_audit')]
    public function auditAction(Request $request): Response
    {
        [$startDate, $endDate, $displayMessage, $todayFilterForm] = $this->getDateRangeFilterForm($request);
        if ($todayFilterForm->isSubmitted()) {
            if (!$todayFilterForm->isValid()) {
                $todayFilterForm->addError(new FormError('Please correct the errors below'));
                return $this->render('program/nph/biobank/audit.html.twig', [
                    'samples' => [],
                    'todayFilterForm' => $todayFilterForm->createView(),
                    'displayMessage' => $displayMessage
                ]);
            }
        }
        $samplesLogs = $this->em->getRepository(NphSampleProcessingStatus::class)->getAuditReport($startDate, $endDate);
        $warningLogs = $this->em->getRepository(NphGenerateOrderWarningLog::class)->getAuditReport($startDate, $endDate);
        $samples = array_merge($samplesLogs, $warningLogs);
        usort($samples, function ($a, $b) {
            return $b->getModifiedTs() <=> $a->getModifiedTs();
        });
        return $this->render('program/nph/biobank/audit.html.twig', [
            'samples' => $samples,
            'todayFilterForm' => $todayFilterForm->createView(),
            'displayMessage' => $displayMessage
        ]);
    }

    #[Route(path: '/review/orders/unfinalized', name: 'nph_biobank_orders_unfinalized')]
    public function ordersUnfinalizedAction(): Response
    {
        $samples = $this->em->getRepository(NphOrder::class)->getUnfinalizedBiobankSamples();
        return $this->render('/program/nph/biobank/orders-unfinalized.html.twig', [
            'samples' => $samples
        ]);
    }

    #[Route(path: '/review/orders/unlocked', name: 'nph_biobank_orders_unlocked')]
    public function ordersUnlockedAction(): Response
    {
        $samples = $this->em->getRepository(NphOrder::class)->getUnlockedBiobankSamples();
        return $this->render('/program/nph/biobank/orders-unlocked.html.twig', [
            'samples' => $samples
        ]);
    }

    #[Route(path: '/review/orders/recent/modified', name: 'nph_biobank_orders_recently_modified')]
    public function ordersRecentlyModifiedAction(): Response
    {
        $samples = $this->em->getRepository(NphOrder::class)->getRecentlyModifiedBiobankSamples($this->getSecurityUser()->getTimeZone());
        return $this->render('/program/nph/biobank/orders-recently-modified.html.twig', [
            'samples' => $samples,
            'modifiedOrdersView' => true
        ]);
    }

    #[Route(path: '/review/orders/downtime', name: 'nph_biobank_orders_downtime')]
    public function ordersDowntimeAction(Request $request): Response
    {
        $userTimeZone = $this->getSecurityUser()->getTimeZone();
        $todayFilterForm = $this->createForm(ReviewTodayFilterType::class, null, ['timezone' => $userTimeZone]);
        $todayFilterForm->handleRequest($request);
        [$startDate, $endDate, $displayMessage, $todayFilterForm] = $this->getDateRangeFilterForm($request);
        if ($todayFilterForm->isSubmitted()) {
            if (!$todayFilterForm->isValid()) {
                $todayFilterForm->addError(new FormError('Please correct the errors below'));
                return $this->render('program/nph/biobank/orders-downtime.html.twig', [
                    'samples' => [],
                    'todayFilterForm' => $todayFilterForm->createView(),
                    'displayMessage' => $displayMessage
                ]);
            }
        }
        $samples = $this->em->getRepository(NphOrder::class)->getDowntimeOrders($startDate, $endDate);
        return $this->render('/program/nph/biobank/orders-downtime.html.twig', [
            'samples' => $samples,
            'todayFilterForm' => $todayFilterForm->createView(),
            'displayMessage' => $displayMessage
        ]);
    }

    #[Route(path: '/review/admin/orders/generation/audit', name: 'nph_biobank_admin_orders_generation_audit')]
    public function adminOrdersGenerationAction(Request $request): Response
    {
        [$startDate, $endDate, $displayMessage, $todayFilterForm] = $this->getDateRangeFilterForm($request);
        if ($todayFilterForm->isSubmitted()) {
            if (!$todayFilterForm->isValid()) {
                $todayFilterForm->addError(new FormError('Please correct the errors below'));
                return $this->render('program/nph/biobank/audit.html.twig', [
                    'samples' => [],
                    'todayFilterForm' => $todayFilterForm->createView(),
                    'displayMessage' => $displayMessage
                ]);
            }
        }
        $orderGenerationEditLogs = $this->em->getRepository(NphAdminOrderEditLog::class)->getOrderEditLogs($startDate, $endDate);
        return $this->render('program/nph/biobank/admin-orders-generation-audit.html.twig', [
            'orderGenerationEditLogs' => $orderGenerationEditLogs,
            'todayFilterForm' => $todayFilterForm->createView(),
            'displayMessage' => $displayMessage
        ]);
    }

    #[Route(path: '/{biobankId}', name: 'nph_biobank_participant')]
    public function participantAction(
        string $biobankId,
        NphOrderService $nphOrderService,
        NphProgramSummaryService $nphProgramSummaryService
    ): Response {
        $participant = $this->nphParticipantSummaryService->search(['biobankId' => $biobankId]);
        if (empty($participant)) {
            throw $this->createNotFoundException();
        }
        $participant = $participant[0];
        $nphOrderInfo = $nphOrderService->getParticipantOrderSummary($participant->id);
        $nphProgramSummary = $nphProgramSummaryService->getProgramSummary();
        $combined = $nphProgramSummaryService->combineOrderSummaryWithProgramSummary($nphOrderInfo, $nphProgramSummary);
        $sampleProcessingStatusByModule = $this->em->getRepository(NphSampleProcessingStatus::class)->getSampleProcessingStatusByModule($participant->id);
        $moduleDietPeriodsStatus = $nphOrderService->getModuleDietPeriodsStatus($participant->id, $participant->module);
        $activeDietPeriod = $nphOrderService->getActiveDietPeriod($moduleDietPeriodsStatus, $participant->module);
        $activeModule = $nphOrderService->getActiveModule($moduleDietPeriodsStatus, $participant->module);
        $generateOrderWarningLogByModule = $this->em->getRepository(NphGenerateOrderWarningLog::class)->getGenerateOrderWarningLogByModule($participant->id);
        return $this->render('program/nph/biobank/participant.html.twig', [
            'participant' => $participant,
            'programSummaryAndOrderInfo' => $combined,
            'moduleDietPeriodsStatus' => $moduleDietPeriodsStatus,
            'sampleProcessingStatusByModule' => $sampleProcessingStatusByModule,
            'generateOrderWarningLogByModule' => $generateOrderWarningLogByModule,
            'activeDietPeriod' => $activeDietPeriod,
            'activeModule' => $activeModule,
            'dietPeriodStatusMap' => NphDietPeriodStatus::$dietPeriodStatusMap,
            'dietToolTipMessages' => NphDietPeriodStatus::$dietToolTipMessages,
        ]);
    }

    #[Route(path: '/samples/aliquot', name: 'nph_biobank_samples_aliquot')]
    public function sampleAliquotLookupAction(NphParticipantSummaryService $nphParticipantSummaryService, Request $request): Response
    {
        $sampleIdForm = $this->createForm(NphSampleLookupType::class, null, [
            'label' => 'Aliquot or Collection Sample ID',
            'placeholder' => 'Scan barcode or enter sample ID'
        ]);
        $sampleIdForm->handleRequest($request);

        if ($sampleIdForm->isSubmitted() && $sampleIdForm->isValid()) {
            $id = $sampleIdForm->get('sampleId')->getData();

            $sample = $this->em->getRepository(NphSample::class)->findOneBy([
                'sampleId' => $id
            ]);
            if (!$sample) {
                $aliquot = $this->em->getRepository(NphAliquot::class)->findOneBy([
                    'aliquotId' => $id
                ]);
                if ($aliquot) {
                    $sample = $aliquot->getNphSample();
                }
            }
            if ($sample) {
                $participantId = $sample->getNphOrder()->getParticipantId();
                $participant = $nphParticipantSummaryService->getParticipantById($participantId);
                if (!$participant) {
                    throw $this->createNotFoundException("Participant not found for sample ID $sample->getSampleId()");
                }
                return $this->redirectToRoute('nph_biobank_sample_finalize', [
                    'biobankId' => $participant->biobankId,
                    'sampleId' => $sample->getId(),
                    'orderId' => $sample->getNphOrder()->getId(),
                ]);
            }
            $this->addFlash('error', 'Sample ID not found');
        }

        return $this->render('program/nph/order/sample-aliquot-lookup.html.twig', [
            'sampleIdForm' => $sampleIdForm->createView(),
            'biobankView' => true
        ]);
    }

    #[Route(path: '/{biobankId}/order/{orderId}/collect', name: 'nph_biobank_order_collect')]
    public function orderCollectDetailsAction(
        string $biobankId,
        string $orderId,
        NphOrderService $nphOrderService,
        NphParticipantSummaryService $nphNphParticipantSummaryService
    ): Response {
        $participant = $nphNphParticipantSummaryService->search(['biobankId' => $biobankId]);
        if (empty($participant)) {
            throw $this->createNotFoundException();
        }
        $participant = $participant[0];
        $order = $this->em->getRepository(NphOrder::class)->find($orderId);
        if (empty($order)) {
            throw $this->createNotFoundException('Order not found.');
        }
        $nphOrderService->loadModules($order->getModule(), $order->getVisitPeriod(), $participant->id, $participant->biobankId);
        $isParticipantWithdrawn = $nphNphParticipantSummaryService->isParticipantWithdrawn($participant, $order->getModule());
        $isParticipantDeactivated = $nphNphParticipantSummaryService->isParticipantDeactivated($participant, $order->getModule());
        return $this->render('program/nph/biobank/order-collect-details.html.twig', [
            'order' => $order,
            'participant' => $participant,
            'timePoints' => $nphOrderService->getTimePoints(),
            'samples' => $nphOrderService->getSamples(),
            'isParticipantDeactivated' => $isParticipantDeactivated,
            'isParticipantWithdrawn' => $isParticipantWithdrawn
        ]);
    }

    #[Route(path: '/{biobankId}/order/{orderId}/sample/{sampleId}/finalize', name: 'nph_biobank_sample_finalize')]
    public function aliquotFinalizeAction(
        string $biobankId,
        string $orderId,
        string $sampleId,
        NphOrderService $nphOrderService,
        NphParticipantSummaryService $nphParticipantSummaryService,
        Request $request
    ) {
        $participant = $nphParticipantSummaryService->search(['biobankId' => $biobankId]);
        $participant = $participant[0];
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
        $nphOrderService->loadModules(
            $order->getModule(),
            $order->getVisitPeriod(),
            $participant->id,
            $participant->biobankId
        );
        $sampleIdForm = $this->createForm(NphSampleLookupType::class, null);
        $sampleCode = $sample->getSampleCode();
        $sampleData = $nphOrderService->getExistingSampleData($sample);
        $dietPeriod = $order->getModule() === 1 ? $order->getVisitPeriod() : substr($order->getVisitPeriod(), 0, 7);
        $canGenerateOrders = $nphOrderService->canGenerateOrders($participant->id, $order->getModule(), $dietPeriod, $participant->module);
        $isSampleDisabled = $sample->isDisabled() || ($sample->getModifyType() !== NphSample::UNLOCK && !$canGenerateOrders);
        $isFormDisabled = $order->getOrderType() === NphOrder::TYPE_STOOL || $order->getOrderType() === NphOrder::TYPE_STOOL_2 ? $isSampleDisabled : true;
        $isFreezeTsDisabled = $order->getOrderType() === NphOrder::TYPE_STOOL || $order->getOrderType() === NphOrder::TYPE_STOOL_2 ? $order->isFreezeTsDisabled($sample->getModifyType()) : false;
        $sampleFinalizeForm = $this->createForm(
            NphSampleFinalizeType::class,
            $sampleData,
            ['sample' => $sampleCode, 'orderType' => $order->getOrderType(), 'timeZone' => $this->getSecurityUser()
                ->getTimezone(), 'aliquots' => $nphOrderService->getAliquots($sampleCode), 'disabled' =>
                $isFormDisabled, 'nphSample' => $sample, 'disableMetadataFields' =>
                $order->isMetadataFieldDisabled(), 'disableStoolCollectedTs' => $sample->getModifyType() !== NphSample::UNLOCK &&
                $order->isStoolCollectedTsDisabled(), 'orderCreatedTs' => $order->getCreatedTs(), 'disableFreezeTs' => $isFreezeTsDisabled, 'biobankView' => true,
            ]
        );
        $sampleFinalizeForm->handleRequest($request);
        if ($sampleFinalizeForm->isSubmitted()) {
            $formData = $sampleData = $sampleFinalizeForm->getData();
            if (!empty($nphOrderService->getAliquots($sampleCode))) {
                if ($nphOrderService->hasAtLeastOneAliquotSample(
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
                if ($nphOrderService->saveFinalization($formData, $sample, true)) {
                    $this->addFlash('success', 'Sample finalized');
                    return $this->redirectToRoute('nph_biobank_sample_finalize', [
                        'biobankId' => $participant->biobankId,
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
                        'participantId' => $participant->id,
                        'orderId' => $orderId,
                        'sampleId' => $sampleId
                    ]);
                }
                $nphSampleModifyForm->addError(new FormError('Please correct the errors below'));
            }
        }

        //Biobank Resubmit
        $notesData[$sampleCode . 'Notes'] = $sampleData[$sampleCode . 'Notes'] ?? null;
        $sampleResubmitForm = $this->createForm(NphSampleResubmitType::class, $notesData, ['sample' => $sampleCode, 'disabled' => !$sample->getRdrId()]);
        $sampleResubmitForm->handleRequest($request);
        if ($sampleResubmitForm->isSubmitted()) {
            $formData = $sampleResubmitForm->getData();
            if ($sampleResubmitForm->isValid()) {
                if ($nphOrderService->saveReFinalization($formData[$sampleCode . 'Notes'], $sample)) {
                    $this->addFlash('success', 'Sample finalized');
                    return $this->redirectToRoute('nph_biobank_sample_finalize', [
                        'biobankId' => $participant->biobankId,
                        'orderId' => $orderId,
                        'sampleId' => $sampleId
                    ]);
                }
                $this->addFlash('error', 'Failed finalizing sample. Please try again.');
                $this->em->refresh($sample);
            } else {
                $sampleResubmitForm->addError(new FormError('Please correct the errors below'));
            }
        }

        return $this->render('program/nph/order/sample-finalize.html.twig', [
            'sampleIdForm' => $sampleIdForm->createView(),
            'sampleFinalizeForm' => $sampleFinalizeForm->createView(),
            'sampleResubmitForm' => $sampleResubmitForm->createView(),
            'sample' => $sample,
            'participant' => $participant,
            'timePoints' => $nphOrderService->getTimePoints(),
            'samples' => $nphOrderService->getSamples(),
            'aliquots' => $nphOrderService->getAliquots($sampleCode),
            'sampleData' => $sampleData,
            'sampleModifyForm' => isset($nphSampleModifyForm) ? $nphSampleModifyForm->createView() : '',
            'modifyType' => $modifyType ?? '',
            'revertForm' => $this->createForm(NphSampleRevertType::class)->createView(),
            'biobankView' => true,
            'isFormDisabled' => $isFormDisabled,
            'visitDiet' => $nphOrderService->getVisitDiet(),
            'isFreezeTsDisabled' => $isFreezeTsDisabled,
            'allowResubmit' => $isFormDisabled && $sample->getModifyType() !== NphSample::UNLOCK
        ]);
    }

    #[Route(path: '/review/export/log', name: 'nph_biobank_review_export_log')]
    public function exportAction(Request $request, LoggerService $loggerService, SiteService $siteService): ?Response
    {
        $exportType = $request->get('exportType');
        $loggerService->log(Log::NPH_BIOBANK_DAILY_REVIEW_EXPORT, ['site' => $siteService->getSiteId(), 'exportType' => $exportType]);
        return new JsonResponse(['success' => true]);
    }

    private function getDateRangeFilterForm(Request $request): array
    {
        $userTimeZone = $this->getSecurityUser()->getTimeZone();
        $todayFilterForm = $this->createForm(ReviewTodayFilterType::class, null, ['timezone' => $userTimeZone]);
        $todayFilterForm->handleRequest($request);
        $startDate = $endDate = null;
        $displayMessage = null;
        if ($todayFilterForm->isSubmitted()) {
            if ($todayFilterForm->isValid()) {
                $startDate = $todayFilterForm->get('start_date')->getData();
                $displayMessage = "Displaying results for {$startDate->format('m/d/Y')}";
                if ($todayFilterForm->get('end_date')->getData()) {
                    $endDate = $todayFilterForm->get('end_date')->getData();
                    $endDate->setTime(23, 59, 59);
                    if ($startDate->diff($endDate)->days >= ReviewTodayFilterType::DATE_RANGE_LIMIT) {
                        $todayFilterForm['start_date']->addError(new FormError('Date range cannot be more than 30 days'));
                    }
                    $displayMessage = "Displaying results from {$startDate->format('m/d/Y')} through {$endDate->format('m/d/Y')}";
                } else {
                    $endDate = clone $startDate;
                    $endDate->modify('+1 day');
                }
            }
        }
        return [$startDate, $endDate, $displayMessage, $todayFilterForm];
    }
}
