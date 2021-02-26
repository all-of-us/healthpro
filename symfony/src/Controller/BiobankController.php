<?php

namespace App\Controller;

use App\Entity\Order;
use App\Service\LoggerService;
use App\Service\OrderService;
use App\Service\ParticipantSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s/biobank")
 */
class BiobankController extends AbstractController
{
    protected $em;
    protected $participantSummaryService;
    protected $orderService;
    protected $loggerService;

    public function __construct(
        EntityManagerInterface $em,
        ParticipantSummaryService $participantSummaryService,
        OrderService $orderService,
        LoggerService $loggerService
    ) {
        $this->em = $em;
        $this->participantSummaryService = $participantSummaryService;
        $this->orderService = $orderService;
        $this->loggerService = $loggerService;
    }

    /**
     * @Route("/{biobankId}", name="biobank_participant")
     */
    public function participantAction(string $biobankId): Response
    {
        $participant = $this->participantSummaryService->search(['biobankId' => $biobankId]);
        if (empty($participant)) {
            throw $this->createNotFoundException();
        }
        $participant = $participant[0];

        // Internal Orders
        $orders = $this->em->getRepository(Order::class)->findBy(['participantId' => $participant->id]);

        // Quanum Orders
        $order = new Order;
        $this->orderService->loadSamplesSchema($order);
        $quanumOrders = $this->orderService->getOrdersByParticipant($participant->id);
        foreach ($quanumOrders as $quanumOrder) {
            if ($quanumOrder->origin === 'careevolution') {
                $orders[] = $this->orderService->loadFromJsonObject($quanumOrder);
            }
        }
        return $this->render('biobank/participant.html.twig', [
            'participant' => $participant,
            'orders' => $orders,
            'biobankView' => true,
            'canViewOrders' => $participant->status || $participant->editExistingOnly
        ]);
    }
}
