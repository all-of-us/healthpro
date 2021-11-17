<?php

namespace App\Controller;

use App\Entity\Order;
use App\Form\OrderLookupIdType;
use App\Service\ParticipantSummaryService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/orders")
 */
class OrderLookupController extends AbstractController
{
    /**
     * @Route("/", name="orders")
     */
    public function ordersAction(
        Request $request,
        EntityManagerInterface $em,
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

            $order = $em->getRepository(Order::class)->findOneBy([
                'orderId' => $id
            ]);
            if ($order) {
                return $this->redirectToRoute('order', [
                    'participantId' => $order->getParticipantId(),
                    'orderId' => $order->getId()
                ]);
            }
            $this->addFlash('error', 'Order ID not found');
        }

        $recentOrders = $em->getRepository(Order::class)->getSiteRecentOrders($siteService->getSiteId());

        foreach ($recentOrders as &$order) {
            $order->participant = $participantSummaryService->getParticipantById($order->getParticipantId());
        }

        return $this->render('orderlookup/orders.html.twig', [
            'idForm' => $idForm->createView(),
            'recentOrders' => $recentOrders
        ]);
    }
}
