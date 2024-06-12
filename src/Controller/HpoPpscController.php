<?php

namespace App\Controller;

use App\Entity\Measurement;
use App\Entity\Order;
use App\Service\MeasurementService;
use App\Service\Ppsc\PpscApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HpoPpscController extends BaseController
{
    public function __construct(
        EntityManagerInterface $em
    ) {
        parent::__construct($em);
    }

    #[Route(path: '/ppsc/participant/{id}', name: 'ppsc_participant')]
    public function ppscParticipantDetailsAction(
        $id,
        PpscApiService $ppscApiService,
        MeasurementService $measurementService,
    ): Response {
        $participant = $ppscApiService->getParticipantById($id);
        if (!$participant) {
            throw $this->createNotFoundException();
        }
        $measurements = $this->em->getRepository(Measurement::class)->getMeasurementsWithoutParent($id);
        $orders = $this->em->getRepository(Order::class)->findBy(['participantId' => $id], ['id' => 'desc']);
        $evaluationUrl = $measurementService->requireBloodDonorCheck() ? 'measurement_blood_donor_check' : 'measurement';
        return $this->render('/program/hpo/ppsc/in-person-enrollment.html.twig', [
            'participant' => $participant,
            'orders' => $orders,
            'measurements' => $measurements,
            'evaluationUrl' => $evaluationUrl
        ]);
    }
}
