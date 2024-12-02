<?php

namespace App\Controller;

use App\Form\DebugParticipantLookupType;
use App\Service\DebugToolsService;
use App\Service\EnvironmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/admin/debug')]
class DebugToolsController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    #[Route(path: '/participants', name: 'admin_debug_participants')]
    public function participantsAction(Request $request, EnvironmentService $env, DebugToolsService $debugToolsService)
    {
        $participantLookupForm = $this->createForm(DebugParticipantLookupType::class);
        $participantLookupForm->handleRequest($request);
        if ($participantLookupForm->isSubmitted() && $participantLookupForm->isValid()) {
            $id = $participantLookupForm->get('participantId')->getData();
            $participant = $debugToolsService->getParticipantById($id);
            if ($participant) {
                return $this->redirectToRoute('admin_debug_participant', ['id' => $id]);
            }
            $this->addFlash('error', 'Participant ID not found');
        }
        return $this->render('admin/debug/participants.html.twig', [
            'idForm' => $participantLookupForm->createView()
        ]);
    }

    #[Route(path: '/participant/{id}', name: 'admin_debug_participant')]
    public function participantAction($id, EnvironmentService $env, DebugToolsService $debugToolsService)
    {
        $participant = $debugToolsService->getParticipantById($id);
        if (!$participant) {
            throw $this->createNotFoundException();
        }
        ksort($participant);
        return $this->render('admin/debug/participant.html.twig', [
            'participant' => $participant
        ]);
    }
}
