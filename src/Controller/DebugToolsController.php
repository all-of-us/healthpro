<?php

namespace App\Controller;

use App\Form\DebugParticipantLookupType;
use App\Service\DebugToolsService;
use App\Service\EnvironmentService;
use App\Service\PatientStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    #[Route(path: '/patientstatus/{participantId}/organization/{organizationId}/json-rdr', name: 'admin_debug_patient_status_json_rdr')]
    public function patientStatusRdrJsonAction($participantId, $organizationId, PatientStatusService $patientStatusService)
    {
        $object = $patientStatusService->getPatientStatus($participantId, $organizationId);
        $response = new JsonResponse($object);
        $response->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_PRETTY_PRINT);
        return $response;
    }

    #[Route(path: '/patientstatus/{participantId}/organization/{organizationId}/history/json-rdr', name: 'admin_debug_patient_status_history_json_rdr')]
    public function patientStatusHistoryRdrJsonAction($participantId, $organizationId, PatientStatusService $patientStatusService)
    {
        $object = $patientStatusService->getPatientStatusHistory($participantId, $organizationId);
        $response = new JsonResponse($object);
        $response->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_PRETTY_PRINT);
        return $response;
    }
}
