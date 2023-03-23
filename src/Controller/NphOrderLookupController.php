<?php

namespace App\Controller;

use App\Entity\NphOrder;
use App\Form\OrderLookupIdType;
use App\Service\Nph\NphParticipantSummaryService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NphOrderLookupController extends AbstractController
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Route("/nph/orders", name="nph_order_lookup")
     */
    public function index(
        Request $request,
        SiteService $siteService,
        NphParticipantSummaryService $participantSummary
    ): Response {
        $idForm = $this->createForm(OrderLookupIdType::class, null);
        $idForm->handleRequest($request);

        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $id = $idForm->get('orderId')->getData();

            $order = $this->em->getRepository(NphOrder::class)->findOneBy([
                'orderId' => $id
            ]);

            if ($order) {
                $participant = $participantSummary->getParticipantById($order->getParticipantId());
                if (!$participant) {
                    throw $this->createNotFoundException('Participant not found.');
                }
                if ($participant->nphPairedSiteSuffix === $siteService->getSiteId()) {
                    return $this->redirectToRoute('nph_order_collect', [
                        'participantId' => $order->getParticipantId(),
                        'orderId' => $order->getId(),
                        'participant' => $participant
                    ]);
                }
                $crossSiteErrorMessage = 'Lookup for this order ID is not permitted because the participant is paired with another site';
            }
            $this->addFlash('error', $crossSiteErrorMessage ?? 'Order ID not found');
        }

        $recentOrders = $this->em->getRepository(NphOrder::class)->getRecentOrdersBySite($siteService->getSiteId());
        foreach ($recentOrders as &$order) {
            $order->participant = $participantSummary->getParticipantById($order->getParticipantId());
        }

        return $this->render('program/nph/order/orderlookup.html.twig', [
            'idForm' => $idForm->createView(),
            'recentOrders' => $recentOrders
        ]);
    }
}
