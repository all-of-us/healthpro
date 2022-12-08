<?php

namespace App\Controller;

use App\Service\Nph\NphOrderService;
use App\Service\Nph\NphProgramSummaryService;
use App\Service\ParticipantSummaryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/nph/participant")
 */
class NphParticipantSummaryController extends AbstractController
{
    /**
     * @Route("/{participantid}", name="nph_participant_summary")
     */
    public function index(
        $participantId,
        ParticipantSummaryService $participantSummaryService,
        NphOrderService $nphOrderService,
        NphProgramSummaryService $nphProgramSummaryService
    ): Response {
        $participant = $participantSummaryService->getParticipantById($participantId);
        $nphOrderInfo = $nphOrderService->getParticipantOrderSummary($participantId);
        $nphProgramSummary = $nphProgramSummaryService->getProgramSummary();
        $combined = $nphProgramSummaryService->combineOrderSummaryWithProgramSummary($nphOrderInfo['order'], $nphProgramSummary);
        return $this->render('program/nph/participant/index.html.twig', [
            'participant' => $participant,
            'programSummaryAndOrderInfo' => $combined
        ]);
    }
}
