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
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
        ParameterBagInterface $params,
        SessionInterface $session,
    ): Response {
        $refresh = $request->query->get('refresh');
        $participant = $ppscApiService->getParticipantById($id, $refresh);
        if (!$participant) {
            throw $this->createNotFoundException();
        }
        $measurements = $this->em->getRepository(Measurement::class)->getMeasurementsWithoutParent($id);
        $orders = $this->em->getRepository(Order::class)->findBy(['participantId' => $id], ['id' => 'desc']);
        $cacheEnabled = $params->has('ppsc_disable_cache') ? !$params->get('ppsc_disable_cache') : true;
        $isDVType = $session->get('siteType') === 'dv';
        return $this->render('/program/hpo/ppsc/in-person-enrollment.html.twig', [
            'participant' => $participant,
            'orders' => $orders,
            'measurements' => $measurements,
            'evaluationUrl' => $measurementService->getMeasurementUrl($participant),
            'cacheEnabled' => $cacheEnabled,
            'isDVType' => $isDVType
        ]);
    }

    #[Route(path: '/ppsc/invalid-site', name: 'ppsc_invalid_site')]
    public function ppscInvalidSiteAction(): Response
    {
        return $this->render('/program/hpo/ppsc/invalid-site.html.twig');
    }
}
