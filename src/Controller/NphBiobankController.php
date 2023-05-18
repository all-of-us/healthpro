<?php

namespace App\Controller;

use App\Entity\NphAliquot;
use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Form\Nph\NphSampleLookupType;
use App\Form\OrderLookupIdType;
use App\Form\ParticipantLookupBiobankIdType;
use App\Service\Nph\NphOrderService;
use App\Service\Nph\NphParticipantSummaryService;
use App\Service\Nph\NphProgramSummaryService;
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
                return $this->redirectToRoute('nph_order_collect', [
                    'participantId' => $order->getParticipantId(),
                    'orderId' => $order->getId()
                ]);
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
    public function participantAction(
        string $biobankId,
        NphOrderService $nphOrderService,
        NphProgramSummaryService $nphProgramSummaryService
    ): Response {
        $participant = $this->nphParticipantSummaryService->search(['biobankId' => $biobankId]);
        if (empty($participant)) {
            throw $this->createNotFoundException();
        }
        $participant = $participant[0];
        $nphOrderInfo = $nphOrderService->getParticipantOrderSummary($participant->id);
        $nphProgramSummary = $nphProgramSummaryService->getProgramSummary();
        $combined = $nphProgramSummaryService->combineOrderSummaryWithProgramSummary($nphOrderInfo, $nphProgramSummary);
        return $this->render('program/nph/biobank/participant.html.twig', [
            'participant' => $participant,
            'programSummaryAndOrderInfo' => $combined
        ]);
    }

    /**
     * @Route("/samples/aliquot", name="nph_biobank_samples_aliquot")
     */
    public function sampleAliquotLookupAction(Request $request): Response
    {
        $sampleIdForm = $this->createForm(NphSampleLookupType::class, null, [
            'label' => 'Aliquot or Collection Sample ID',
            'placeholder' => 'Scan barcode or enter sample ID'
        ]);
        $sampleIdForm->handleRequest($request);

        if ($sampleIdForm->isSubmitted() && $sampleIdForm->isValid()) {
            $id = $sampleIdForm->get('sampleId')->getData();

            $sample = $this->em->getRepository(NphSample::class)->findOneBy([
                'sampleId' => $id
            ]);
            if (!$sample) {
                $aliquot = $this->em->getRepository(NphAliquot::class)->findOneBy([
                    'aliquotId' => $id
                ]);
                $sample = $aliquot->getNphSample();
            }
            if ($sample) {
                //TODO Redirect to Andrew's biobank aliquot finalize page
                dd($sample);
            }
            $this->addFlash('error', 'Sample ID not found');
        }

        return $this->render('program/nph/order/sample-aliquot-lookup.html.twig', [
            'sampleIdForm' => $sampleIdForm->createView()
        ]);
    }

    /**
     * @Route("/{biobankId}/order/{orderId}/collect", name="nph_biobank_order_collect")
     */
    public function orderCollectDetailsAction(
        $biobankId,
        $orderId,
        NphOrderService $nphOrderService,
        NphParticipantSummaryService $nphNphParticipantSummaryService
    ): Response {
        $participant = $nphNphParticipantSummaryService->search(['biobankId' => $biobankId]);
        if (empty($participant)) {
            throw $this->createNotFoundException();
        }
        $participant = $participant[0];
        $order = $this->em->getRepository(NphOrder::class)->find($orderId);
        if (empty($order)) {
            throw $this->createNotFoundException('Order not found.');
        }
        $nphOrderService->loadModules($order->getModule(), $order->getVisitType(), $participant->id, $participant->biobankId);
        return $this->render('program/nph/biobank/order-collect-details.html.twig', [
            'order' => $order,
            'participant' => $participant,
            'timePoints' => $nphOrderService->getTimePoints(),
            'samples' => $nphOrderService->getSamples(),
        ]);
    }
}
