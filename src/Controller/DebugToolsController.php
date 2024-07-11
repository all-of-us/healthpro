<?php

namespace App\Controller;

use App\Entity\Measurement;
use App\Entity\Order;
use App\Form\DebugParticipantLookupType;
use App\Form\MissingMeasurementsType;
use App\Form\MissingOrdersType;
use App\Model\Measurement\Fhir;
use App\Service\DebugToolsService;
use App\Service\EnvironmentService;
use App\Service\MeasurementService;
use App\Service\OrderService;
use App\Service\ParticipantSummaryService;
use App\Service\PatientStatusService;
use App\Service\RdrApiService;
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

    #[Route(path: '/missing/measurements', name: 'admin_debug_missing_measurements')]
    public function missingMeasurementsAction(Request $request, MeasurementService $measurementsService, RdrApiService $rdrApiService, ParticipantSummaryService $participantSummaryService)
    {
        $missing = $this->em->getRepository(Measurement::class)->getMissingMeasurements();
        $choices = [];
        foreach ($missing as $physicalMeasurements) {
            $choices[$physicalMeasurements->getId()] = $physicalMeasurements->getId();
        }
        $form = $this->createForm(MissingMeasurementsType::class, null, ['choices' => $choices]);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $ids = $form->get('ids')->getData();
            if (!empty($ids) && $form->isValid()) {
                $repository = $this->em->getRepository(Measurement::class);
                foreach ($ids as $id) {
                    $measurement = $repository->findOneBy(['id' => $id]);
                    if (!$measurement) {
                        continue;
                    }
                    $participant = $participantSummaryService->getParticipantById($measurement->getParticipantId());
                    $measurementsService->load($measurement, $participant);
                    $parentRdrId = null;
                    if ($measurement->getParentId()) {
                        $parentEvaluation = $repository->findOneBy(['id' => $measurement->getParentId()]);
                        if ($parentEvaluation) {
                            $parentRdrId = $parentEvaluation->getRdrId();
                        }
                    }
                    // Get FHIR bundle
                    $fhir = $measurement->getFhir($measurement->getFinalizedTs(), $parentRdrId);

                    // Send measurements to RDR
                    if ($rdrEvalId = $measurementsService->createMeasurement($fhir)) {
                        $updateMeasurement = $repository->find($measurement->getId());
                        $updateMeasurement->setRdrId($rdrEvalId);
                        $updateMeasurement->setFhirVersion(Fhir::CURRENT_VERSION);
                        $this->em->flush();
                        $this->em->clear();
                        $this->addFlash('success', "#{$id} successfully sent to RDR");
                    } else {
                        $this->addFlash('error', "#{$id} failed sending to RDR: " . $rdrApiService->getLastError());
                    }
                }
                return $this->redirectToRoute('admin_debug_missing_measurements');
            }
            $this->addFlash('error', 'Please select at least one physical measurements');
        }
        return $this->render('admin/debug/missing-measurements.html.twig', [
            'missing' => $missing,
            'form' => $form->createView()
        ]);
    }

    #[Route(path: '/missing/orders', name: 'admin_debug_missing_orders')]
    public function missingOrdersAction(Request $request, OrderService $orderService, RdrApiService $rdrApiService)
    {
        $missing = $this->em->getRepository(Order::class)->getMissingOrders();
        $choices = [];
        foreach ($missing as $orders) {
            $choices[$orders->getId()] = $orders->getId();
        }
        $form = $this->createForm(MissingOrdersType::class, null, ['choices' => $choices]);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $ids = $form->get('ids')->getData();
            if (!empty($ids) && $form->isValid()) {
                $repository = $this->em->getRepository(Order::class);
                // Send orders to RDR
                foreach ($ids as $id) {
                    $order = $repository->find(['id' => $id]);
                    if (!$order) {
                        continue;
                    }
                    // Get order payload
                    $orderService->loadSamplesSchema($order);
                    $orderRdrObject = $order->getRdrObject();

                    // Send order to RDR
                    if ($rdrId = $orderService->createOrder($orderRdrObject)) {
                        $updateOrder = $repository->find($order->getId());
                        $updateOrder->setRdrId($rdrId);
                        $this->em->flush();
                        $this->em->clear();
                        $this->addFlash('success', "#{$id} successfully sent to RDR");
                    } elseif ($rdrApiService->getLastErrorCode() === 409) {
                        $rdrOrder = $orderService->getOrder($order->getParticipantId(), $order->getMayoId());
                        // Check if order exists in RDR
                        if (!empty($rdrOrder) && $rdrOrder->id === $order->getMayoId()) {
                            $updateOrder = $repository->find($order->getId());
                            $updateOrder->setRdrId($order->getMayoId());
                            $this->em->flush();
                            $this->em->clear();
                            $this->addFlash('success', "#{$id} successfully reconciled");
                        } else {
                            $this->addFlash('error', "#{$id} failed to finalize: " . $rdrApiService->getLastError());
                        }
                    } else {
                        $this->addFlash('error', "#{$id} failed sending to RDR: " . $rdrApiService->getLastError());
                    }
                }
                return $this->redirectToRoute('admin_debug_missing_orders');
            }
            $this->addFlash('error', 'Please select at least one order');
        }
        return $this->render('admin/debug/missing-orders.html.twig', [
            'missing' => $missing,
            'form' => $form->createView()
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
