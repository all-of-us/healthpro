<?php

namespace App\Controller;

use App\Entity\Order;
use App\Form\OrderLookupIdType;
use App\Service\ParticipantSummaryService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OrderLookupController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    /**
     * @Route("/orders", name="orders")
     * @Route("/read/orders", name="read_orders")
     */
    public function ordersAction(
        Request $request,
        SiteService $siteService,
        ParticipantSummaryService $participantSummaryService
    ): Response {
        $idForm = $this->createForm(OrderLookupIdType::class, null);
        $idForm->handleRequest($request);

        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $id = $idForm->get('orderId')->getData();

            // New barcodes include a 4-digit sample identifier appended to the 10 digit order id
            // If the string matches this format, remove the sample identifier to get the order id
            if (preg_match('/^\d{14}$/', $id)) {
                $id = substr($id, 0, 10);
            }

            $order = $this->em->getRepository(Order::class)->findOneBy([
                'orderId' => $id
            ]);
            if ($order) {
                $redirectRoute = $this->isReadOnly() ? 'read_order' : 'order';
                return $this->redirectToRoute($redirectRoute, [
                    'participantId' => $order->getParticipantId(),
                    'orderId' => $order->getId()
                ]);
            }
            $this->addFlash('error', 'Order ID not found');
        }

        $recentOrders = [];
        if (!$this->isReadOnly()) {
            $recentOrders = $this->em->getRepository(Order::class)->getSiteRecentOrders($siteService->getSiteId());

            foreach ($recentOrders as &$order) {
                $order->participant = $participantSummaryService->getParticipantById($order->getParticipantId());
            }
        }

        return $this->render('orderlookup/orders.html.twig', [
            'idForm' => $idForm->createView(),
            'recentOrders' => $recentOrders,
            'readOnlyView' => $this->isReadOnly()
        ]);
    }
}
