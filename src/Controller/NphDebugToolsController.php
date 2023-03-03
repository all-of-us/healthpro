<?php

namespace App\Controller;

use App\Form\DebugParticipantLookupType;
use App\Service\DebugToolsService;
use App\Service\EnvironmentService;
use App\Service\Nph\NphParticipantSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/nph/admin/debug")
 */
class NphDebugToolsController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    /**
     * @Route("/participants", name="nph_admin_debug_participants")
     */
    public function participantsAction(Request $request, NphParticipantSummaryService $nphParticipantSummaryService): Response
    {
        $participantLookupForm = $this->createForm(DebugParticipantLookupType::class, null, ['placeholder' => '10000000000000']);
        $participantLookupForm->handleRequest($request);
        if ($participantLookupForm->isSubmitted() && $participantLookupForm->isValid()) {
            $id = $participantLookupForm->get('participantId')->getData();
            $participant = $nphParticipantSummaryService->getAllParticipantDetailsById($id);
            if ($participant) {
                return $this->redirectToRoute('nph_admin_debug_participant', ['id' => $id]);
            }
            $this->addFlash('error', 'Participant ID not found');
        }
        return $this->render('program/nph/admin/debug/participants.html.twig', [
            'idForm' => $participantLookupForm->createView()
        ]);
    }

    /**
     * @Route("/participant/{id}", name="nph_admin_debug_participant")
     */
    public function participantAction($id, NphParticipantSummaryService $nphParticipantSummaryService): Response
    {
        $participant = $nphParticipantSummaryService->getAllParticipantDetailsById($id);
        if (!$participant) {
            throw $this->createNotFoundException();
        }
        return $this->render('program/nph/admin/debug/participant.html.twig', [
            'participant' => $participant
        ]);
    }
}
