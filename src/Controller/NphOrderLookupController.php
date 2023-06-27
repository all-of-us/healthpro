<?php

namespace App\Controller;

use App\Entity\NphOrder;
use App\Form\OrderLookupIdType;
use App\Service\Nph\NphParticipantSummaryService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
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

    #[Route(path: '/nph/orders', name: 'nph_order_lookup')]
    public function index(
        Request $request,
        SiteService $siteService,
        NphParticipantSummaryService $participantSummary
    ): Response {
        $recentOrders = $this->em->getRepository(NphOrder::class)->getRecentOrdersBySite($siteService->getSiteId());
        foreach ($recentOrders as &$order) {
            $order->participant = $participantSummary->getParticipantById($order->getParticipantId());
        }
        $idForm = $this->getIdForm($request, $siteService, $participantSummary);
        if ($idForm instanceof RedirectResponse) {
            return $idForm;
        }
        return $this->generateOrderLookupView('program/nph/order/orderlookup.html.twig', $idForm, $recentOrders);
    }

    private function getIdForm(
        Request $request,
        SiteService $siteService,
        NphParticipantSummaryService $participantSummary
    ): FormInterface|RedirectResponse {
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
                        'orderId' => $order->getId()
                    ]);
                }
                $crossSiteErrorMessage = 'Lookup for this order ID is not permitted because the participant is paired with another site';
            }
            $this->addFlash('error', $crossSiteErrorMessage ?? 'Order ID not found');
        }
        return $idForm;
    }
    private function generateOrderLookupView(string $formpath, FormInterface $idForm, array $recentOrders = null): Response
    {
        return $this->render($formpath, [
            'idForm' => $idForm->createView(),
            'recentOrders' => $recentOrders
        ]);
    }
}
