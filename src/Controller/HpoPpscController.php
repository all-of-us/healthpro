<?php

namespace App\Controller;

use App\Entity\Measurement;
use App\Entity\Order;
use App\Service\MeasurementService;
use App\Service\Ppsc\PpscApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HpoPpscController extends BaseController
{
    public function __construct(
        EntityManagerInterface $em
    ) {
        parent::__construct($em);
    }

    #[Route(path: '/ppsc/participant/{id}', name: 'participant')]
    #[Route(path: '/read/participant/{id}', name: 'read_participant', methods: ['GET'])]
    public function ppscParticipantDetailsAction(
        $id,
        Request $request,
        PpscApiService $ppscApiService,
        MeasurementService $measurementService,
        ParameterBagInterface $params
    ): Response {
        $refresh = $request->query->get('refresh');
        $participant = $ppscApiService->getParticipantById($id, $refresh);
        if (!$participant) {
            throw $this->createNotFoundException();
        }
        $measurements = $this->em->getRepository(Measurement::class)->getMeasurementsWithoutParent($id);
        $orders = $this->em->getRepository(Order::class)->findBy(['participantId' => $id], ['id' => 'desc']);
        $evaluationUrl = $measurementService->requireBloodDonorCheck() ? 'measurement_blood_donor_check' : 'measurement';
        $cacheEnabled = $params->has('ppsc_disable_cache') ? !$params->get('ppsc_disable_cache') : true;
        return $this->render('/program/hpo/ppsc/in-person-enrollment.html.twig', [
            'participant' => $participant,
            'orders' => $orders,
            'measurements' => $measurements,
            'evaluationUrl' => $evaluationUrl,
            'cacheEnabled' => $cacheEnabled
        ]);
    }
}
