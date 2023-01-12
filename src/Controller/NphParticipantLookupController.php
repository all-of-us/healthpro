<?php

namespace App\Controller;

use App\Form\Nph\NphParticipantLookupIdType;
use App\Service\Nph\NphParticipantSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class NphParticipantLookupController extends BaseController
{
    protected $nphParticipantSummaryService;

    public function __construct(EntityManagerInterface $em, NphParticipantSummaryService $nphParticipantSummaryService)
    {
        parent::__construct($em);
        $this->nphParticipantSummaryService = $nphParticipantSummaryService;
    }

    /**
     * @Route("/nph/participants", name="nph_participants")
     */
    public function participantsAction(Request $request)
    {
        $idForm = $this->createForm(NphParticipantLookupIdType::class, null);
        $idForm->handleRequest($request);

        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $id = $idForm->get('participantId')->getData();
            $participant = $this->nphParticipantSummaryService->getParticipantById($id);
            if ($participant) {
                return $this->redirectToRoute('nph_participant_summary', ['participantId' => $id]);
            }
            $this->addFlash('error', 'Participant ID not found');
        }

        return $this->render('program/nph/participantlookup/participants.html.twig', [
            'idForm' => $idForm->createView()
        ]);
    }
}
