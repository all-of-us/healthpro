<?php

namespace App\Controller;

use App\Form\DebugParticipantLookupType;
use App\Form\MissingEvaluationsType;
use App\Form\MissingOrdersType;
use App\Repository\EvaluationRepository;
use App\Repository\OrderRepository;
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

    /**
     * @Route("/missing/measurements", name="admin_debug_missing_measurements")
     */
    public function missingMeasurementsAction(Request $request, EvaluationRepository $evaluationRepository)
    {
        $missing = $evaluationRepository->getMissingEvaluations();
        $choices = [];
        foreach ($missing as $physicalMeasurements) {
            $choices[$physicalMeasurements->getId()] = $physicalMeasurements->getId();
        }
        $form = $this->createForm(MissingEvaluationsType::class, null, ['choices' => $choices]);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $ids = $form->get('ids')->getData();
            if (!empty($ids) && $form->isValid()) {
                // TODO
                // Send evaluations to RDR
            } else {
                $this->addFlash('error', 'Please select at least one physical measurements');
            }
        }
        return $this->render('admin/debug/missing-measurements.html.twig', [
            'missing' => $missing,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/missing/orders", name="admin_debug_missing_orders")
     */
    public function missingOrdersAction(Request $request, OrderRepository $orderRepository)
    {
        $missing = $orderRepository->getMissingOrders();
        $choices = [];
        foreach ($missing as $orders) {
            $choices[$orders->getId()] = $orders->getId();
        }
        $form = $this->createForm(MissingOrdersType::class, null, ['choices' => $choices]);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $ids = $form->get('ids')->getData();
            if (!empty($ids) && $form->isValid()) {
                // TODO
                // Send orders to RDR
            } else {
                $this->addFlash('error', 'Please select at least one order');
            }
        }
        return $this->render('admin/debug/missing-orders.html.twig', [
            'missing' => $missing,
            'form' => $form->createView()
        ]);
    }
}
