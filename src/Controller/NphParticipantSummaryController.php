<?php

namespace App\Controller;

use App\Audit\Log;
use App\Form\Nph\NphCrossSiteAgreeType;
use App\Service\LoggerService;
use App\Service\Nph\NphOrderService;
use App\Service\Nph\NphParticipantSummaryService;
use App\Service\Nph\NphProgramSummaryService;
use App\Service\SiteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/nph/participant")
 */
class NphParticipantSummaryController extends AbstractController
{
    /**
     * @Route("/{participantId}", name="nph_participant_summary")
     */
    public function index(
        $participantId,
        Request $request,
        SessionInterface $session,
        LoggerService $loggerService,
        SiteService $siteService,
        NphParticipantSummaryService $nphParticipantSummaryService,
        NphOrderService $nphOrderService,
        NphProgramSummaryService $nphProgramSummaryService
    ): Response {
        $participant = $nphParticipantSummaryService->getParticipantById($participantId);
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
        return $this->render('program/nph/participant/index.html.twig', [
            'participant' => $participant,
            'programSummaryAndOrderInfo' => $combined,
            'hasNoParticipantAccess' => $hasNoParticipantAccess,
            'agreeForm' => $agreeForm->createView(),
        ]);
    }
}
