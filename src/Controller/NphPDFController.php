<?php

namespace App\Controller;

use App\Service\Nph\NphOrderService;
use App\Service\Nph\NphParticipantSummaryService;
use App\Service\PDFService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NphPDFController extends AbstractController
{
    //TODO: Refactor to work off shyams NPHOrderService->getExistingOrdersData
    /**
     * @Route("/nph/participant/{participantId}/render_pdf/module/{module}/visit/{visit}", name="nph_render_pdf")
     */
    public function render_pdf(
        $participantId,
        $module,
        $visit,
        PDFService $PDF,
        NphOrderService $nphOrderService,
        NphParticipantSummaryService $nphParticipantSummaryService
    ): Response {
        $OrderPDF = $PDF->batchPDF($nphOrderService->getParticipantOrderSummaryByModuleAndVisit($participantId, $module, $visit)['order'], $nphParticipantSummaryService->getParticipantById($participantId), $module, $visit);
        return new Response($OrderPDF, Response::HTTP_OK, ['content-type' => 'application/pdf']);
    }

    /**
     * @Route("/nph/participant/{participantId}/render_pdf/module/{module}/visit/{visit}/{sampleGroup}", name="nph_render_pdf_sample_group")
     */
    public function renderPDFSampleGroup(
        $participantId,
        $module,
        $visit,
        $sampleGroup,
        PDFService $PDF,
        NphOrderService $nphOrderService,
        NphParticipantSummaryService $nphParticipantSummaryService
    ): Response {
        $OrderPDF = $PDF->batchPDF($nphOrderService->getParticipantOrderSummaryByModuleVisitAndSampleGroup($participantId, $module, $visit, $sampleGroup)['order'], $nphParticipantSummaryService->getParticipantById($participantId), $module, $visit);
        return new Response($OrderPDF, Response::HTTP_OK, ['content-type' => 'application/pdf']);
    }
}
