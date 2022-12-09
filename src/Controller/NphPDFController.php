<?php

namespace App\Controller;

use App\Service\Nph\NphOrderService;
use App\Service\ParticipantSummaryService;
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
    public function render_pdf($participantId, $module, $visit, PDFService $PDF, NphOrderService $nphOrderService, ParticipantSummaryService $participantSummaryService): Response
    {
        $OrderPDF = $PDF->batchPDF($nphOrderService->getParticipantOrderSummaryByModuleAndVisit($participantId, $module, $visit)['order'], $participantSummaryService->getParticipantById($participantId), $module, $visit);
        return new Response($OrderPDF, Response::HTTP_OK, ['content-type' => 'application/pdf']);
    }
}
