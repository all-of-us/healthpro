<?php

namespace App\Controller;

use App\Form\DebugParticipantLookupType;
use App\Service\DebugToolsService;
use App\Service\EnvironmentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s/admin/debug")
 */
class DebugToolsController extends AbstractController
{
    /**
     * @Route("/participants", name="admin_debug_participants")
     */
    public function participantsAction(Request $request, EnvironmentService $env, DebugToolsService $debugToolsService)
    {
        if ($env->isProd()) {
            throw $this->createNotFoundException();
        }
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

    /**
     * @Route("/participant/{id}", name="admin_debug_participant")
     */
    public function participantAction($id, EnvironmentService $env, DebugToolsService $debugToolsService)
    {
        if ($env->isProd()) {
            throw $this->createNotFoundException();
        }
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
