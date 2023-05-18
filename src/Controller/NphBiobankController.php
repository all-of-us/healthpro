<?php

namespace App\Controller;

use App\Entity\NphOrder;
use App\Form\OrderLookupIdType;
use App\Form\ParticipantLookupBiobankIdType;
use App\Service\Nph\NphParticipantSummaryService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/nph/biobank")
 */
class NphBiobankController extends BaseController
{
    protected NphParticipantSummaryService $nphParticipantSummaryService;
    protected ParameterBagInterface $params;

    public function __construct(
        EntityManagerInterface $em,
        NphParticipantSummaryService $nphParticipantSummaryService,
        ParameterBagInterface $params
    ) {
        parent::__construct($em);
        $this->nphParticipantSummaryService = $nphParticipantSummaryService;
        $this->params = $params;
    }

    /**
     * @Route("/", name="nph_biobank_home")
     */
    public function indexAction(): Response
    {
        return $this->render('program/nph/biobank/index.html.twig');
    }

    /**
     * @Route("/participants", name="nph_biobank_participants")
     */
    public function participantsAction(Request $request): Response
    {
        $bioBankIdPrefix = $this->params->has('nph_biobank_id_prefix') ? $this->params->get('nph_biobank_id_prefix') : null;
        $idForm = $this->createForm(ParticipantLookupBiobankIdType::class, null, ['bioBankIdPrefix' => $bioBankIdPrefix]);
        $idForm->handleRequest($request);
        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $searchParameters = $idForm->getData();
            $searchResults = $this->nphParticipantSummaryService->search($searchParameters);
            if (!empty($searchResults)) {
                return $this->redirectToRoute('nph_biobank_participant', [
                    'biobankId' => $searchResults[0]->biobankId
                ]);
            }
            $this->addFlash('error', 'Biobank ID not found');
        }
        return $this->render('program/nph/biobank/participants.html.twig', [
            'idForm' => $idForm->createView()
        ]);
    }

    /**
     * @Route("/orderlookup", name="nph_biobank_order_lookup")
     */
    public function orderLookupAction(
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
                        'orderId' => $order->getId()
                    ]);
                }
            }
            $this->addFlash('error', 'Order ID not found');
        }
        return $this->render(
            'program/nph/order/orderlookup.html.twig',
            [
                'idForm' => $idForm->createView(),
                'recentOrders' => null,
            ]
        );
    }

    /**
     * @Route("/{biobankId}", name="nph_biobank_participant")
     */
    public function participantAction(string $biobankId): Response
    {
        //TODO Implement biobank participant details page
        return $this->render('program/nph/biobank/participant.html.twig');
    }
}
