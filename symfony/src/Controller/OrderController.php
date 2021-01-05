<?php

namespace App\Controller;

use App\Service\ParticipantSummaryService;
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
    public function orderCheck($participantId, ParticipantSummaryService $participantSummaryService)
    {
        $participant = $participantSummaryService->getParticipantById($participantId);
        //dd($participant);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        return $this->render('order/check.html.twig', [
            'participant' => $participant
        ]);
    }
}
