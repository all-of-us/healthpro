<?php

namespace App\Controller;

use App\Entity\NphAliquot;
use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Form\Nph\NphAliquotLookupType;
use App\Form\Nph\NphSampleLookupType;
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
        $sampleIdForm = $this->getSampleAliquotIdForm(
            $request,
            $siteService,
            $participantSummary,
            NphSampleLookupType::class,
            'sampleId',
            'Collection Sample ID'
        );
        if ($sampleIdForm instanceof RedirectResponse) {
            return $sampleIdForm;
        }
        $aliquotIdForm = $this->getSampleAliquotIdForm(
            $request,
            $siteService,
            $participantSummary,
            NphAliquotLookupType::class,
            'aliquotId',
            'Aliquot ID'
        );
        if ($aliquotIdForm instanceof RedirectResponse) {
            return $aliquotIdForm;
        }
        return $this->generateOrderLookupView(
            'program/nph/order/orderlookup.html.twig',
            $idForm,
            $sampleIdForm,
            $aliquotIdForm,
            $recentOrders
        );
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
                if ($participantSummary->isParticipantWithdrawn($participant, $order->getModule())) {
                    $flashMessage = 'This participant has been withdrawn. Data cannot  be viewed, collected, or sent for this participant.';
                } else {
                    if ($participant->nphPairedSiteSuffix === $siteService->getSiteId()) {
                        return $this->redirectToRoute('nph_order_collect', [
                            'participantId' => $order->getParticipantId(),
                            'orderId' => $order->getId()
                        ]);
                    }
                    $flashMessage = 'Lookup for this Order ID is not permitted because the participant is paired with another site';
                }
            }
            $this->addFlash('error', $flashMessage ?? 'Order ID not found');
        }
        return $idForm;
    }

    private function getSampleAliquotIdForm(
        Request $request,
        SiteService $siteService,
        NphParticipantSummaryService $nphParticipantSummaryService,
        string $formType,
        string $lookupKey,
        string $fieldLabel
    ): FormInterface|RedirectResponse {
        $form = $this->createForm($formType, null, ['label' => $fieldLabel]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $id = $form->get($lookupKey)->getData();
            $entityClass = ($formType === NphSampleLookupType::class) ? NphSample::class : NphAliquot::class;
            $entity = $this->em->getRepository($entityClass)->findOneBy([
                $lookupKey => $id
            ]);
            if ($entity) {
                $sample = ($entity instanceof NphSample) ? $entity : $entity->getNphSample();
                if ($sample) {
                    $participantId = $sample->getNphOrder()->getParticipantId();
                    $participant = $nphParticipantSummaryService->getParticipantById($participantId);
                    if (!$participant) {
                        throw $this->createNotFoundException('Participant not found.');
                    }
                    if ($nphParticipantSummaryService->isParticipantWithdrawn($participant, $sample->getNphOrder()->getModule())) {
                        $flashMessage = 'This participant has been withdrawn. Data cannot  be viewed, collected, or sent for this participant.';
                    } else {
                        if ($participant->nphPairedSiteSuffix === $siteService->getSiteId()) {
                            return $this->redirectToRoute('nph_sample_finalize', [
                                'participantId' => $sample->getNphOrder()->getParticipantId(),
                                'orderId' => $sample->getNphOrder()->getId(),
                                'sampleId' => $sample->getId()
                            ]);
                        }
                        $flashMessage = "Lookup for this $fieldLabel is not permitted because the participant is paired with another site";
                    }
                }
            }
            $this->addFlash('error', $flashMessage ?? "$fieldLabel not found");
        }
        return $form;
    }

    private function generateOrderLookupView(
        string $formpath,
        FormInterface $idForm,
        FormInterface $sampleIdForm,
        FormInterface $aliquotIdForm,
        array $recentOrders = null
    ): Response {
        return $this->render($formpath, [
            'idForm' => $idForm->createView(),
            'sampleIdForm' => $sampleIdForm->createView(),
            'aliquotIdForm' => $aliquotIdForm->createView(),
            'recentOrders' => $recentOrders
        ]);
    }
}
