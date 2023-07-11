<?php

namespace App\Controller;

use App\Audit\Log;
use App\Entity\NphDlw;
use App\Form\Nph\NphCrossSiteAgreeType;
use App\Service\LoggerService;
use App\Service\Nph\NphOrderService;
use App\Service\Nph\NphParticipantSummaryService;
use App\Service\Nph\NphProgramSummaryService;
use App\Service\SiteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        return $this->render('program/nph/participant/index.html.twig', [
            'participant' => $participant,
            'programSummaryAndOrderInfo' => $combined,
            'hasNoParticipantAccess' => $hasNoParticipantAccess,
            'agreeForm' => $agreeForm->createView(),
            'cacheEnabled' => $cacheEnabled,
            'dlwSummary' => $dlwSummary
        ]);
    }
}
