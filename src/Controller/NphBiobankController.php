<?php

namespace App\Controller;

use App\Entity\NphAliquot;
use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Form\Nph\NphSampleFinalizeType;
use App\Form\Nph\NphSampleLookupType;
use App\Form\Nph\NphSampleModifyType;
use App\Form\Nph\NphSampleRevertType;
use App\Form\OrderLookupIdType;
use App\Form\ParticipantLookupBiobankIdType;
use App\Service\Nph\NphOrderService;
use App\Service\Nph\NphParticipantReviewService;
use App\Service\Nph\NphParticipantSummaryService;
use App\Service\Nph\NphProgramSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/nph/biobank')]
class NphBiobankController extends BaseController
{
    protected NphParticipantSummaryService $nphParticipantSummaryService;
    protected ParameterBagInterface $params;
    protected NphParticipantReviewService $nphParticipantReviewService;

    public function __construct(
        EntityManagerInterface $em,
        NphParticipantSummaryService $nphParticipantSummaryService,
        ParameterBagInterface $params,
        NphParticipantReviewService $nphParticipantReviewService
    ) {
        parent::__construct($em);
        $this->nphParticipantSummaryService = $nphParticipantSummaryService;
        $this->params = $params;
        $this->nphParticipantReviewService = $nphParticipantReviewService;
    }

    #[Route(path: '/', name: 'nph_biobank_home')]
    public function indexAction(): Response
    {
        return $this->render('program/nph/biobank/index.html.twig');
    }

    #[Route(path: '/participants', name: 'nph_biobank_participants')]
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

    #[Route(path: '/orderlookup', name: 'nph_biobank_order_lookup')]
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
                return $this->redirectToRoute('nph_biobank_order_collect', [
                    'biobankId' => $participant->biobankId,
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
                'biobankView' => true,
            ]
        );
    }

    #[Route(path: '/review/orders/today', name: 'nph_biobank_orders_today')]
    public function ordersTodayAction(): Response
    {
        $samples = $this->em->getRepository(NphOrder::class)->getTodaysBiobankOrders($this->getSecurityUser()->getTimeZone());
        return $this->render('/program/nph/biobank/orders-today.html.twig', [
            'samples' => $samples
        ]);
    }

    #[Route(path: '/review/orders/unfinalized', name: 'nph_biobank_orders_unfinalized')]
    public function ordersUnfinalizedAction(): Response
    {
        $samples = $this->em->getRepository(NphOrder::class)->getUnfinalizedBiobankSamples();
        return $this->render('/program/nph/biobank/orders-unfinalized.html.twig', [
            'samples' => $samples
        ]);
    }

    #[Route(path: '/review/orders/unlocked', name: 'nph_biobank_orders_unlocked')]
    public function ordersUnlockedAction(): Response
    {
        $samples = $this->em->getRepository(NphOrder::class)->getUnlockedBiobankSamples();
        return $this->render('/program/nph/biobank/orders-unlocked.html.twig', [
            'samples' => $samples
        ]);
    }

    #[Route(path: '/review/orders/recent/modified', name: 'nph_biobank_orders_recently_modified')]
    public function ordersRecentlyModifiedAction(): Response
    {
        $samples = $this->em->getRepository(NphOrder::class)->getRecentlyModifiedBiobankSamples($this->getSecurityUser()->getTimeZone());
        return $this->render('/program/nph/biobank/orders-recently-modified.html.twig', [
            'samples' => $samples,
            'modifiedOrdersView' => true
        ]);
    }

    #[Route(path: '/{biobankId}', name: 'nph_biobank_participant')]
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

    #[Route(path: '/samples/aliquot', name: 'nph_biobank_samples_aliquot')]
    public function sampleAliquotLookupAction(NphParticipantSummaryService $nphParticipantSummaryService, Request $request): Response
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
                if ($aliquot) {
                    $sample = $aliquot->getNphSample();
                }
            }
            if ($sample) {
                $participantId = $sample->getNphOrder()->getParticipantId();
                $participant = $nphParticipantSummaryService->getParticipantById($participantId);
                if (!$participant) {
                    throw $this->createNotFoundException("Participant not found for sample ID $sample->getSampleId()");
                }
                return $this->redirectToRoute('nph_biobank_sample_finalize', [
                    'biobankId' => $participant->biobankId,
                    'sampleId' => $sample->getId(),
                    'orderId' => $sample->getNphOrder()->getId(),
                ]);
            }
            $this->addFlash('error', 'Sample ID not found');
        }

        return $this->render('program/nph/order/sample-aliquot-lookup.html.twig', [
            'sampleIdForm' => $sampleIdForm->createView(),
            'biobankView' => true
        ]);
    }

    #[Route(path: '/{biobankId}/order/{orderId}/collect', name: 'nph_biobank_order_collect')]
    public function orderCollectDetailsAction(
        string $biobankId,
        string $orderId,
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

    #[Route(path: '/{biobankId}/order/{orderId}/sample/{sampleId}/finalize', name: 'nph_biobank_sample_finalize')]
    public function aliquotFinalizeAction(
        string $biobankId,
        string $orderId,
        string $sampleId,
        NphOrderService $nphOrderService,
        NphParticipantSummaryService $nphParticipantSummaryService,
        Request $request
    ) {
        $participant = $nphParticipantSummaryService->search(['biobankId' => $biobankId]);
        $participant = $participant[0];
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $order = $this->em->getRepository(NphOrder::class)->find($orderId);
        if (empty($order)) {
            throw $this->createNotFoundException('Order not found.');
        }
        $sample = $this->em->getRepository(NphSample::class)->findOneBy([
            'nphOrder' => $order, 'id' => $sampleId
        ]);
        $nphOrderService->loadModules(
            $order->getModule(),
            $order->getVisitType(),
            $participant->id,
            $participant->biobankId
        );
        $sampleIdForm = $this->createForm(NphSampleLookupType::class, null);
        $sampleCode = $sample->getSampleCode();
        $sampleData = $nphOrderService->getExistingSampleData($sample);
        $isDietStartedOrCompleted = $nphOrderService->isDietStartedOrCompleted($participant->{'module' . $order->getModule() . 'DietStatus'});
        $isSampleDisabled = $sample->isDisabled() || ($sample->getModifyType() !== NphSample::UNLOCK && !$isDietStartedOrCompleted);
        $isFormDisabled = $order->getOrderType() === NphOrder::TYPE_STOOL ? $isSampleDisabled : true;
        $sampleFinalizeForm = $this->createForm(
            NphSampleFinalizeType::class,
            $sampleData,
            ['sample' => $sampleCode, 'orderType' => $order->getOrderType(), 'timeZone' => $this->getSecurityUser()
                ->getTimezone(), 'aliquots' => $nphOrderService->getAliquots($sampleCode), 'disabled' =>
                $isFormDisabled, 'nphSample' => $sample, 'disableMetadataFields' =>
                $order->isMetadataFieldDisabled(), 'disableStoolCollectedTs' => $sample->getModifyType() !== NphSample::UNLOCK &&
                $order->isStoolCollectedTsDisabled(), 'orderCreatedTs' => $order->getCreatedTs()
            ]
        );
        $sampleFinalizeForm->handleRequest($request);
        if ($sampleFinalizeForm->isSubmitted()) {
            if ($sample->isDisabled()) {
                throw $this->createAccessDeniedException();
            }
            $formData = $sampleData = $sampleFinalizeForm->getData();
            if (!empty($nphOrderService->getAliquots($sampleCode))) {
                if ($sample->getModifyType() !== NphSample::UNLOCK && $nphOrderService->hasAtLeastOneAliquotSample(
                    $formData,
                    $sampleCode
                ) === false) {
                    $sampleFinalizeForm['aliquotError']->addError(new FormError('Please enter at least one aliquot'));
                } elseif ($nphOrderService->hasDuplicateAliquotsInForm($formData, $sampleCode)) {
                    $sampleFinalizeForm['aliquotError']->addError(new FormError('Please enter a unique aliquot barcode'));
                } else {
                    $duplicate = $nphOrderService->checkDuplicateAliquotId($formData, $sampleCode, $sample->getNphAliquotIds());
                    if ($duplicate) {
                        $sampleFinalizeForm[$duplicate['aliquotCode']][$duplicate['key']]->addError(new FormError('Aliquot ID already exists'));
                    }
                }
            }
            if ($sampleFinalizeForm->isValid()) {
                if ($nphOrderService->saveFinalization($formData, $sample, true)) {
                    $this->addFlash('success', 'Sample finalized');
                    return $this->redirectToRoute('nph_biobank_sample_finalize', [
                        'biobankId' => $participant->biobankId,
                        'orderId' => $orderId,
                        'sampleId' => $sampleId
                    ]);
                }
                $this->addFlash('error', 'Failed finalizing sample. Please try again.');
                $this->em->refresh($sample);
            } else {
                $sampleFinalizeForm->addError(new FormError('Please correct the errors below'));
            }
        }

        if ($request->query->has('modifyType')) {
            $modifyType = $request->query->get('modifyType');
            if ($modifyType !== NphSample::UNLOCK || $sample->canUnlock() === false) {
                throw $this->createNotFoundException();
            }
            if ($modifyType === $sample->getModifyType()) {
                throw $this->createNotFoundException();
            }
            $nphSampleModifyForm = $this->createForm(NphSampleModifyType::class, null, ['type' => $modifyType]);
            $nphSampleModifyForm->handleRequest($request);
            if ($nphSampleModifyForm->isSubmitted()) {
                $sampleModifyData = $nphSampleModifyForm->getData();
                if ($nphSampleModifyForm->isValid()) {
                    $nphOrderService->saveSampleModification($sampleModifyData, NphSample::UNLOCK, $sample);
                    $successText = $sample::$modifySuccessText;
                    $this->addFlash('success', "Sample {$successText[$modifyType]}");
                    return $this->redirectToRoute('nph_sample_finalize', [
                        'participantId' => $participant->id,
                        'orderId' => $orderId,
                        'sampleId' => $sampleId
                    ]);
                }
                $nphSampleModifyForm->addError(new FormError('Please correct the errors below'));
            }
        }

        return $this->render('program/nph/order/sample-finalize.html.twig', [
            'sampleIdForm' => $sampleIdForm->createView(),
            'sampleFinalizeForm' => $sampleFinalizeForm->createView(),
            'sample' => $sample,
            'participant' => $participant,
            'timePoints' => $nphOrderService->getTimePoints(),
            'samples' => $nphOrderService->getSamples(),
            'aliquots' => $nphOrderService->getAliquots($sampleCode),
            'sampleData' => $sampleData,
            'sampleModifyForm' => isset($nphSampleModifyForm) ? $nphSampleModifyForm->createView() : '',
            'modifyType' => $modifyType ?? '',
            'revertForm' => $this->createForm(NphSampleRevertType::class)->createView(),
            'biobankView' => true,
            'isFormDisabled' => $isFormDisabled,
            'visitDiet' => $nphOrderService->getVisitDiet()
        ]);
    }
}
