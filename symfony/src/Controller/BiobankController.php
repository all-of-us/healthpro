<?php

namespace App\Controller;

use App\Entity\Order;
use App\Form\OrderLookupType;
use App\Form\ParticipantLookupBiobankIdType;
use App\Service\LoggerService;
use App\Service\OrderService;
use App\Service\ParticipantSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
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
    protected $params;

    public function __construct(
        EntityManagerInterface $em,
        ParticipantSummaryService $participantSummaryService,
        OrderService $orderService,
        LoggerService $loggerService,
        ParameterBagInterface $params
    ) {
        $this->em = $em;
        $this->participantSummaryService = $participantSummaryService;
        $this->orderService = $orderService;
        $this->loggerService = $loggerService;
        $this->params = $params;
    }

    /**
     * @Route("/", name="biobank_home")
     */
    public function indexAction(): Response
    {
        return $this->render('biobank/index.html.twig');
    }

    /**
     * @Route("/participants", name="biobank_participants")
     */
    public function participantsAction(Request $request): Response
    {
        $bioBankIdPrefix = $this->params->has('biobank_id_prefix') ? $this->params->get('biobank_id_prefix') : null;
        $idForm = $this->createForm(ParticipantLookupBiobankIdType::class, null, ['bioBankIdPrefix' => $bioBankIdPrefix]);
        $idForm->handleRequest($request);
        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $searchParameters = $idForm->getData();
            $searchResults = $this->participantSummaryService->search($searchParameters);
            if (!empty($searchResults)) {
                return $this->redirectToRoute('biobank_participant', [
                    'biobankId' => $searchResults[0]->biobankId
                ]);
            }
            $this->addFlash('error', 'Biobank ID not found');
        }
        return $this->render('biobank/participants.html.twig', [
            'idForm' => $idForm->createView()
        ]);
    }

    /**
     * @Route("/orders", name="biobank_orders")
     */
    public function ordersAction(Request $request): Response
    {
        $idForm = $this->createForm(OrderLookupType::class, null);
        $idForm->handleRequest($request);
        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $id = $idForm->get('orderId')->getData();

            // New barcodes include a 4-digit sample identifier appended to the 10 digit order id
            // If the string matches this format, remove the sample identifier to get the order id
            if (preg_match('/^\d{14}$/', $id)) {
                $id = substr($id, 0, 10);
            }
            // Internal Order
            $order = $this->em->getRepository(Order::class)->findOneBy([
                'orderId' => $id
            ]);
            if ($order) {
                return $this->redirectToRoute('biobank_order', [
                    'biobankId' => $order->getBiobankId(),
                    'orderId' => $order->getId()
                ]);
            }
            // Quanum Orders
            $order = new Order;
            $this->orderService->loadSamplesSchema($order);
            $quanumOrders = $this->orderService->getOrders([
                'kitId' => $id,
                'origin' => 'careevolution'
            ]);
            if (isset($quanumOrders[0])) {
                $order = $this->orderService->loadFromJsonObject($quanumOrders[0]);
                $participant = $this->participantSummaryService->getParticipantById($order->getParticipantId());
                if ($participant->biobankId) {
                    return $this->redirectToRoute('biobank_quanumOrder', [
                        'biobankId' => $participant->biobankId,
                        'orderId' => $order->getRdrId()
                    ]);
                }
            }
            $this->addFlash('error', 'Order ID not found');
        }

        return $this->render('biobank/orders.html.twig', [
            'idForm' => $idForm->createView()
        ]);
    }

    /**
     * @Route("/review/orders/today", name="biobank_orders_today")
     */
    public function ordersTodayAction(Request $request)
    {
        return '';
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
