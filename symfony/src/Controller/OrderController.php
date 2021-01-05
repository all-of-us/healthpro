<?php

namespace App\Controller;

use App\Service\ParticipantSummaryService;
use App\Service\SiteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s")
 */
class OrderController extends AbstractController
{
    /**
     * @Route("/participant/{participantId}/order/check", name="order_check")
     */
    public function orderCheck($participantId, ParticipantSummaryService $participantSummaryService, SiteService $siteService)
    {
        $participant = $participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        if (!$participant->status || $siteService->isTestSite() || $participant->activityStatus === 'deactivated') {
            throw $this->createAccessDeniedException('Participant ineligible for order create.');
        }
        return $this->render('order/check.html.twig', [
            'participant' => $participant
        ]);
    }
}
