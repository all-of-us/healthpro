<?php

namespace App\Controller;

use App\Audit\Log;
use App\Entity\NphDlw;
use App\Entity\NphGenerateOrderWarningLog;
use App\Entity\NphOrder;
use App\Entity\NphSampleProcessingStatus;
use App\Form\Nph\NphCrossSiteAgreeType;
use App\Form\Nph\NphGenerateOrderWarningLogType;
use App\Form\Nph\NphSampleProcessCompleteType;
use App\Helper\NphDietPeriodStatus;
use App\Nph\Order\Nomenclature;
use App\Service\LoggerService;
use App\Service\Nph\NphOrderService;
use App\Service\Nph\NphParticipantSummaryService;
use App\Service\Nph\NphProgramSummaryService;
use App\Service\SiteService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/nph/participant')]
class NphParticipantSummaryController extends BaseController
{
    #[Route(path: '/{participantId}', name: 'nph_participant_summary')]
    public function index(
        $participantId,
        Request $request,
        SessionInterface $session,
        LoggerService $loggerService,
        SiteService $siteService,
        NphParticipantSummaryService $nphParticipantSummaryService,
        NphOrderService $nphOrderService,
        NphProgramSummaryService $nphProgramSummaryService,
        ParameterBagInterface $params
    ): Response {
        $refresh = $request->query->get('refresh');
        $participant = $nphParticipantSummaryService->getParticipantById($participantId, $refresh);
        if ($refresh) {
            return $this->redirectToRoute('nph_participant_summary', ['participantId' => $participantId]);
        }
        if ($participant === false) {
            throw $this->createNotFoundException();
        }
        $agreeForm = $this->createForm(NphCrossSiteAgreeType::class, null);
        $agreeForm->handleRequest($request);
        if ($agreeForm->isSubmitted() && $agreeForm->isValid()) {
            $session->set('agreeCrossSite_' . $participantId, true);
            $loggerService->log(Log::NPH_CROSS_SITE_PARTICIPANT_AGREE, [
                'participantId' => $participantId,
                'site' => $participant->nphPairedSiteSuffix
            ]);
            return $this->redirectToRoute('nph_participant_summary', [
                'participantId' => $participantId
            ]);
        }
        $nphOrderInfo = $nphOrderService->getParticipantOrderSummary($participantId);
        $nphProgramSummary = $nphProgramSummaryService->getProgramSummary();
        $combined = $nphProgramSummaryService->combineOrderSummaryWithProgramSummary($nphOrderInfo, $nphProgramSummary);
        $sampleStatusCounts = $nphOrderService->getSampleStatusCounts($combined);
        $isCrossSite = $participant->nphPairedSiteSuffix !== $siteService->getSiteId();
        $hasNoParticipantAccess = $isCrossSite && empty($session->get('agreeCrossSite_' . $participantId));
        if ($hasNoParticipantAccess) {
            $loggerService->log(Log::NPH_CROSS_SITE_PARTICIPANT_ATTEMPT, [
                'participantId' => $participantId,
                'site' => $participant->nphPairedSiteSuffix
            ]);
        } elseif ($isCrossSite) {
            $loggerService->log(Log::NPH_CROSS_SITE_PARTICIPANT_VIEW, [
                'participantId' => $participantId,
                'site' => $participant->nphPairedSiteSuffix
            ]);
        }
        $dlwCollection = $this->em->getRepository(NphDlw::class)->findBy(['NphParticipant' => $participantId]);
        $dlwSummary = $nphOrderService->generateDlwSummary($dlwCollection);
        $cacheEnabled = $params->has('rdr_disable_cache') ? !$params->get('rdr_disable_cache') : true;
        $sampleProcessCompleteForm = $this->createForm(NphSampleProcessCompleteType::class, null);
        $sampleProcessCompleteForm->handleRequest($request);
        if ($sampleProcessCompleteForm->isSubmitted() && $sampleProcessCompleteForm->isValid()) {
            $formData = $sampleProcessCompleteForm->getData();
            $nphOrderService->saveSampleProcessingStatus($participantId, $participant->biobankId, $formData, $sampleStatusCounts);
            return $this->redirectToRoute('nph_participant_summary', [
                'participantId' => $participantId
            ]);
        }
        $sampleProcessingStatusByModule = $this->em->getRepository(NphSampleProcessingStatus::class)->getSampleProcessingStatusByModule($participantId);
        $moduleDietPeriodsStatus = $nphOrderService->getModuleDietPeriodsStatus($participantId, $participant->module);

        $orderGenerateWarningLogForm = $this->createForm(NphGenerateOrderWarningLogType::class, null);
        $orderGenerateWarningLogForm->handleRequest($request);
        if ($orderGenerateWarningLogForm->isSubmitted() && $orderGenerateWarningLogForm->isValid()) {
            $formData = $orderGenerateWarningLogForm->getData();
            $nphOrderService->saveGenerateOrderWarningLog($participantId, $participant->biobankId, $formData, $sampleStatusCounts);
            return $this->redirect($formData['redirectLink']);
        }
        $generateOrderWarningLogByModule = $this->em->getRepository(NphGenerateOrderWarningLog::class)
            ->getGenerateOrderWarningLogByModule($participantId);

        $activeDietPeriod = $nphOrderService->getActiveDietPeriod($moduleDietPeriodsStatus, $participant->module);
        $activeModule = $nphOrderService->getActiveModule($moduleDietPeriodsStatus, $participant->module);

        return $this->render('program/nph/participant/index.html.twig', [
            'participant' => $participant,
            'programSummaryAndOrderInfo' => $combined,
            'hasNoParticipantAccess' => $hasNoParticipantAccess,
            'agreeForm' => $agreeForm->createView(),
            'sampleProcessCompleteForm' => $sampleProcessCompleteForm->createView(),
            'orderGenerateWarningLogForm' => $orderGenerateWarningLogForm->createView(),
            'sampleProcessingStatusByModule' => $sampleProcessingStatusByModule,
            'generateOrderWarningLogByModule' => $generateOrderWarningLogByModule,
            'moduleDietPeriodsStatus' => $moduleDietPeriodsStatus,
            'activeDietPeriod' => $activeDietPeriod,
            'activeModule' => $activeModule,
            'dietPeriodStatusMap' => NphDietPeriodStatus::$dietPeriodStatusMap,
            'dietToolTipMessages' => NphDietPeriodStatus::$dietToolTipMessages,
            'cacheEnabled' => $cacheEnabled,
            'dlwSummary' => $dlwSummary,
            'sampleStatusCounts' => $sampleStatusCounts,
            'dietMapper' => Nomenclature::$dietMapper
        ]);
    }

    #[Route(path: '/{participantId}/module/{module}/bootstrap/{bootstrapVersion}/view/', name: 'quickView')]
    public function quickViewAction(
        string $participantId,
        string $module,
        string $bootstrapVersion,
        NphParticipantSummaryService $nphNphParticipantSummaryService,
        NphOrderService $nphOrderService
    ): Response {
        $participant = $nphNphParticipantSummaryService->getParticipantById($participantId);
        $orders = $this->em->getRepository(NphOrder::class)->findBy(['participantId' => $participantId, 'module' =>
            $module], ['visitPeriod' => 'ASC']);
        return $this->render('program/nph/participant/quick-view.html.twig', [
            'orders' => $orders,
            'module' => $module,
            'bootstrapVersion' => $bootstrapVersion,
            'participant' => $participant,
            'visitTimePointSamples' => $nphOrderService->getModuleVisitTimePointSamples($module, $participantId, $participant->biobankId),
            'timePointsInfo' => $nphOrderService->getModuleTimePoints($module, $participantId, $participant->biobankId)
        ]);
    }
}
