<?php

namespace App\Controller;

use App\Audit\Log;
use App\Entity\Measurement;
use App\Entity\Order;
use App\Entity\Site;
use App\Form\OrderCreateType;
use App\Form\OrderModifyType;
use App\Form\OrderRevertType;
use App\Form\OrderType;
use App\Service\EnvironmentService;
use App\Service\LoggerService;
use App\Service\MeasurementService;
use App\Service\OrderService;
use App\Service\ParticipantSummaryService;
use App\Service\Ppsc\PpscApiService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;

class OrderController extends BaseController
{
    protected $orderService;
    protected $participantSummaryService;
    protected $loggerService;
    protected $siteService;
    protected PpscApiService $ppscApiService;

    public function __construct(
        EntityManagerInterface $em,
        OrderService $orderService,
        ParticipantSummaryService $participantSummaryService,
        LoggerService $loggerService,
        SiteService $siteService,
        PpscApiService $ppscApiService,
    ) {
        parent::__construct($em);
        $this->orderService = $orderService;
        $this->participantSummaryService = $participantSummaryService;
        $this->loggerService = $loggerService;
        $this->siteService = $siteService;
        $this->ppscApiService = $ppscApiService;
    }

    public function loadOrder($participantId, $orderId)
    {
        $participant = $this->ppscApiService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $orderRepository = $this->em->getRepository(Order::class);
        $order = $orderRepository->find($orderId);
        if (empty($order)) {
            throw $this->createNotFoundException('Order not found.');
        }
        $this->orderService->setParticipant($participant);
        $this->orderService->loadSamplesSchema($order);
        return $order;
    }

    #[Route(path: '/ppsc/participant/{participantId}/order/check', name: 'order_check')]
    public function orderCheck($participantId, RequestStack $requestStack): Response
    {
        $participant = $this->ppscApiService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        return $this->render('order/check.html.twig', [
            'participant' => $participant,
            'siteType' => $requestStack->getSession()->get('siteType'),
        ]);
    }

    #[Route(path: '/ppsc/participant/{participantId}/order/create', name: 'order_create')]
    public function orderCreateAction($participantId, Request $request, SessionInterface $session, MeasurementService $measurementService, ParameterBagInterface $params)
    {
        $participant = $this->ppscApiService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        if ($request->request->has('show-blood-tubes') && $request->request->has('show-saliva-tubes')) {
            $showBloodTubes = $request->request->get('show-blood-tubes') === 'yes' ? true : false;
            $showSalivaTubes = $request->request->get('show-saliva-tubes') === 'yes' ? true : false;
        } else {
            throw $this->createAccessDeniedException('Participant ineligible for order create.');
        }
        $physicalMeasurement = $this->em->getRepository(Measurement::class)->getMostRecentFinalizedNonNullWeight($participant->id);
        if ($physicalMeasurement) {
            $measurementService->load($physicalMeasurement, $participant);
        }
        $order = new Order();
        $this->orderService->loadSamplesSchema($order, $participant, $physicalMeasurement);
        $createForm = $this->createForm(OrderCreateType::class, null, [
            'orderType' => $session->get('orderType'),
            'samples' => $order->getSamples(),
            'showBloodTubes' => $showBloodTubes,
            'nonBloodSamples' => $order::$nonBloodSamples,
            'pediatric' => $participant->isPediatric,
        ]);
        $showCustom = false;
        $createForm->handleRequest($request);
        if (!$createForm->isSubmitted() && !$this->get('security.csrf.token_manager')->isTokenValid(new CsrfToken(
            'orderCheck',
            $request->request->get('csrf_token')
        ))) {
            throw $this->createAccessDeniedException('Participant ineligible for order create.');
        }
        if ($createForm->isSubmitted() && $createForm->isValid()) {
            if ($request->request->has('existing')) {
                $orderRepository = $this->em->getRepository(Order::class);
                if (empty($createForm['kitId']->getData())) {
                    $createForm['kitId']['first']->addError(new FormError('Please enter a kit order ID'));
                } elseif ($orderRepository->findOneBy(['orderId' => $createForm['kitId']->getData()])) {
                    $createForm['kitId']['first']->addError(new FormError('This order ID already exists'));
                } else {
                    $order->setOrderId($createForm['kitId']->getData());
                    $order->setType('kit');
                    if (!$showBloodTubes) {
                        $order->setRequestedSamples(json_encode([$order->getUrineSample()]));
                    }
                }
            } else {
                if ($request->request->has('custom')) {
                    $showCustom = true;
                    $requestedSamples = $createForm['samples']->getData();
                    if (empty($requestedSamples) || !is_array($requestedSamples)) {
                        $createForm['samples']->addError(new FormError('Please select at least one sample'));
                    } else {
                        $order->setRequestedSamples(json_encode($requestedSamples));
                    }
                } elseif ($request->request->has('saliva')) {
                    $order->setType('saliva');
                }
                if ($participant->isPediatric) {
                    if ($request->request->has('blood')) {
                        $order->setRequestedSamples(json_encode($order->getPediatricBloodSamples()));
                    } elseif ($request->request->has('urine')) {
                        $order->setRequestedSamples(json_encode($order->getPediatricUrineSamples()));
                    } elseif ($request->request->has('saliva')) {
                        $order->setRequestedSamples(json_encode($order->getPediatricSalivaSamples()));
                    }
                }
            }
            if ($createForm->isValid()) { // @phpstan-ignore-line
                $order->setUser($this->getUserEntity());
                $order->setSite($this->siteService->getSiteId());
                $order->setParticipantId($participant->id);
                $order->setBiobankId($participant->biobankId);
                $order->setCreatedTs(new \DateTime());
                $order->setCreatedTimezoneId($this->getUserEntity()->getTimezoneId());
                if ($session->get('siteType') !== 'dv' || (float) $params->get('order_samples_version') <= 3.1) {
                    $order->setVersion($order->getCurrentVersion());
                }
                $order->setAgeInMonths($participant->ageInMonths);
                if ($session->get('orderType') === 'hpo') {
                    $order->setProcessedCentrifugeType(Order::SWINGING_BUCKET);
                }
                if ($session->get('siteType') === 'dv' && $this->siteService->isDiversionPouchSite()) {
                    $order->setType('diversion');
                }
                if ($session->get('siteType') === 'dv' && $this->siteService->isDiversionPouchSite() === false && $order->getCurrentVersion() > 3.1 && $order->getVersion() === null) {
                    $order->setType(Order::TUBE_SELECTION_TYPE);
                }
                if (empty($order->getOrderId())) {
                    $order->setOrderId($this->orderService->generateId());
                }
                $this->em->persist($order);
                $this->em->flush();
                $orderId = $order->getId();
                if ($orderId && $session->get('siteType')) {
                    $this->loggerService->log(Log::ORDER_CREATE, $orderId);
                    return $this->redirectToRoute('order', [
                        'participantId' => $participant->id,
                        'orderId' => $orderId
                    ]);
                }
                $this->addFlash('error', 'Failed to create order.');
            }
        }
        if (!$showBloodTubes) {
            $showCustom = true;
        }
        return $this->render('order/create.html.twig', [
            'participant' => $participant,
            'createForm' => $createForm->createView(),
            'showCustom' => $showCustom,
            'samplesInfo' => $order->getSamplesInformation(),
            'showBloodTubes' => $showBloodTubes,
            'showSalivaTubes' => $showSalivaTubes,
            'version' => $order->getVersion(),
            'salivaInstructions' => $order->getSalivaInstructions(),
            'orderCurrentVersion' => $order->getCurrentVersion(),
            'isPediatricOrder' => $order->isPediatricOrder(),
            'showPediatricBloodTubes' => count($order->getPediatricBloodSamples()) > 0,
        ]);
    }

    #[Route(path: '/ppsc/participant/{participantId}/order/{orderId}/print/labels', name: 'order_print_labels')]
    #[Route(path: '/read/participant/{participantId}/order/{orderId}/print/labels', name: 'read_order_print_labels')]
    public function orderPrintLabelsAction($participantId, $orderId): Response
    {
        $order = $this->loadOrder($participantId, $orderId);
        if ($order->isDisabled() || $order->isUnlocked()) {
            throw $this->createAccessDeniedException();
        }
        if (!in_array('print_labels', $order->getAvailableSteps())) {
            // 404 because print is not a valid route for kit orders regardless of state
            throw $this->createAccessDeniedException();
        }
        $result = $this->orderService->getLabelsPdf();
        if (!$order->getPrintedTs() && $result['status'] === 'success') {
            $order->setPrintedTs(new \DateTime());
            $this->em->persist($order);
            $this->em->flush();
            $this->loggerService->log(Log::ORDER_EDIT, $orderId);
        }
        $errorMessage = !empty($result['errorMessage']) ? $result['errorMessage'] : '';
        return $this->render('order/print-labels.html.twig', [
            'participant' => $this->orderService->getParticipant(),
            'order' => $order,
            'processTabClass' => $order->getProcessTabClass(),
            'errorMessage' => $errorMessage,
            'readOnlyView' => $this->isReadOnly(),
            'isPediatricOrder' => $order->isPediatricOrder(),
        ]);
    }

    #[Route(path: '/ppsc/participant/{participantId}/order/{orderId}/labels/pdf', name: 'order_labels_pdf')]
    #[Route(path: '/read/participant/{participantId}/order/{orderId}/labels/pdf', name: 'read_order_labels_pdf')]
    public function orderLabelsPdfAction($participantId, $orderId, Request $request, ParameterBagInterface $params)
    {
        $order = $this->loadOrder($participantId, $orderId);
        if ($order->isDisabled() || $order->isUnlocked()) {
            throw $this->createAccessDeniedException();
        }
        if (!in_array('print_labels', $order->getAvailableSteps())) {
            // 404 because print is not a valid route for kit orders regardless of state
            throw $this->createAccessDeniedException();
        }
        if ($params->has('ml_mock_order')) {
            return $this->redirect($request->getBaseUrl() . '/assets/SampleLabels.pdf');
        }
        $result = $this->orderService->getLabelsPdf();
        if ($result['status'] === 'success') {
            return new Response($result['pdf'], 200, ['Content-Type' => 'application/pdf']);
        }
        $html = '<html><body style="font-family: Helvetica Neue,Helvetica,Arial,sans-serif"><strong>' . $result['errorMessage'] . '</strong></body></html>';
        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    #[Route(path: '/ppsc/participant/{participantId}/order/{orderId}/collect', name: 'order_collect')]
    #[Route(path: '/read/participant/{participantId}/order/{orderId}/collect', name: 'read_order_collect', methods: ['GET'])]
    public function orderCollectAction($participantId, $orderId, Request $request, Session $session, ParameterBagInterface $params)
    {
        $order = $this->loadOrder($participantId, $orderId);
        $nextStep = ($order->getType() === 'saliva' || $order->isPediatricOrder()) ? 'finalize' : 'process';
        $wasNextStepAvailable = in_array($nextStep, $order->getAvailableSteps());
        if (!in_array('collect', $order->getAvailableSteps())) {
            return $this->redirectToRoute('order', [
                'participantId' => $participantId,
                'orderId' => $orderId
            ]);
        }
        $formData = $this->orderService->getOrderFormData('collected');
        $collectForm = $this->createOrderCollectForm($order, $formData, $request, $session, $params, 'collected');
        $collectForm->handleRequest($request);
        $updatedTubes = false;
        if ($collectForm->isSubmitted()) {
            if ($order->isDisabled()) {
                throw $this->createAccessDeniedException();
            }
            if (isset($collectForm['collectedNotes']) && $type = $this->orderService->getParticipant()->checkIdentifiers($collectForm['collectedNotes']->getData())) {
                $label = Order::$identifierLabel[$type[0]];
                $collectForm['collectedNotes']->addError(new FormError("Please remove participant $label \"$type[1]\""));
            }
            if (!$request->request->has('updateTubes') && ($collectForm->has('orderVersion') && $collectForm->get('orderVersion')->getData() !== $order->getVersion())) {
                $this->orderService->updateOrderVersion($order, $collectForm['orderVersion']->getData(), $collectForm);
                $collectForm = $this->createOrderCollectForm($order, $formData, $request, $session, $params, 'collected');
                $collectForm->handleRequest($request);
            }
            if ($collectForm->isValid()) {
                if ($request->request->has('updateTubes')) {
                    $order = $this->orderService->updateOrderVersion($order, $collectForm['orderVersion']->getData(), $collectForm);
                    $formData = $this->orderService->getOrderFormData('collected');
                    if ($collectForm->has('collectedTs') && !empty($collectForm->get('collectedTs')->getData())) {
                        $formData['collectedTs'] = $collectForm->get('collectedTs')->getData();
                    }
                    $collectForm = $this->createOrderCollectForm($order, $formData, $request, $session, $params, 'collected');
                    $updatedTubes = true;
                } else {
                    $this->orderService->setOrderUpdateFromForm('collected', $collectForm);
                    if (!$order->isUnlocked()) {
                        $order->setCollectedUser($this->getUserEntity());
                        $order->setCollectedSite($this->siteService->getSiteId());
                    }
                    // Save order
                    $this->em->persist($order);
                    $this->em->flush();
                    $this->loggerService->log(Log::ORDER_EDIT, $orderId);
                    $this->addFlash('notice', 'Order collection updated');
                    $redirectRoute = 'order_collect';
                    if (!$wasNextStepAvailable && in_array($nextStep, $order->getAvailableSteps())) {
                        $redirectRoute = "order_{$nextStep}";
                    }
                    return $this->redirectToRoute($redirectRoute, [
                        'participantId' => $participantId,
                        'orderId' => $orderId
                    ]);
                }
            } else {
                $collectForm->addError(new FormError('Please correct the errors below'));
            }
        }
        return $this->render('order/collect.html.twig', [
            'participant' => $this->orderService->getParticipant(),
            'order' => $order,
            'collectForm' => $collectForm->createView(),
            'samplesInfo' => $order->getSamplesInformation(),
            'version' => $order->getVersion(),
            'processTabClass' => $order->getProcessTabClass(),
            'revertForm' => $this->createForm(OrderRevertType::class, null)->createView(),
            'readOnlyView' => $this->isReadOnly(),
            'isPediatricOrder' => $order->isPediatricOrder(),
            'inactiveSiteFormDisabled' => $this->orderService->inactiveSiteFormDisabled(),
            'updatedTubes' => $updatedTubes
        ]);
    }

    #[Route(path: '/ppsc/participant/{participantId}/order/{orderId}/process', name: 'order_process')]
    #[Route(path: '/read/participant/{participantId}/order/{orderId}/process', name: 'read_order_process', methods: ['GET'])]
    public function orderProcessAction($participantId, $orderId, Request $request)
    {
        $order = $this->loadOrder($participantId, $orderId);
        $wasFinalizeStepAvailable = in_array('finalize', $order->getAvailableSteps());
        if (!in_array('process', $order->getAvailableSteps())) {
            return $this->redirectToRoute('order', [
                'participantId' => $participantId,
                'orderId' => $orderId
            ]);
        }
        $formData = $this->orderService->getOrderFormData('processed');
        $processForm = $this->createForm(OrderType::class, $formData, [
            'step' => 'processed',
            'order' => $order,
            'em' => $this->em,
            'timeZone' => $this->getSecurityUser()->getTimezone(),
            'siteId' => $this->siteService->getSiteId(),
            'disabled' => $this->isReadOnly() || $this->orderService->inactiveSiteFormDisabled()
        ]);
        $processForm->handleRequest($request);
        if ($processForm->isSubmitted()) {
            if ($order->isDisabled()) {
                throw $this->createAccessDeniedException();
            }
            if ($processForm->has('processedSamples')) {
                $processedSampleTimes = $processForm->get('processedSamplesTs')->getData();
                foreach ($processForm->get('processedSamples')->getData() as $sample) {
                    if (empty($processedSampleTimes[$sample])) {
                        $processForm->get('processedSamples')->addError(new FormError('Please specify time of blood processing completion for each sample'));
                        break;
                    }
                }
            }
            if ($type = $this->orderService->getParticipant()->checkIdentifiers($processForm['processedNotes']->getData())) {
                $label = Order::$identifierLabel[$type[0]];
                $processForm['processedNotes']->addError(new FormError("Please remove participant $label \"$type[1]\""));
            }
            if ($processForm->isValid()) {
                $this->orderService->setOrderUpdateFromForm('processed', $processForm);
                $order->setProcessedTs(empty($order->getProcessedTs()) ? new \DateTime() : $order->getProcessedTs());
                // Set processed_ts to the most recent processed sample time if exists
                if (!empty($order->getProcessedSamplesTs())) {
                    $processedSamplesTs = json_decode($order->getProcessedSamplesTs(), true);
                    if (is_array($processedSamplesTs) && !empty($processedSamplesTs)) {
                        $processedTs = new \DateTime();
                        $processedTs->setTimestamp(max($processedSamplesTs));
                        $order->setProcessedTs($processedTs);
                    }
                }
                $order->setProcessedTimezoneId($this->getUserEntity()->getTimezoneId());
                if (!$order->isUnlocked()) {
                    $order->setProcessedUser($this->getUserEntity());
                    $order->setProcessedSite($this->siteService->getSiteId());
                }
                if ($order->getType() !== 'saliva') {
                    $site = $this->em->getRepository(Site::class)->findOneBy([
                        'deleted' => 0,
                        'googleGroup' => $this->siteService->getSiteId()
                    ]);
                    if ($processForm->has('processedCentrifugeType')) {
                        $order->setProcessedCentrifugeType($processForm['processedCentrifugeType']->getData());
                    } elseif (!empty($site->getCentrifugeType())) {
                        $order->setProcessedCentrifugeType($site->getCentrifugeType());
                    }
                }
                $this->em->persist($order);
                $this->em->flush();
                $this->loggerService->log(Log::ORDER_EDIT, $orderId);
                $this->addFlash('notice', 'Order processing updated');
                $redirectRoute = 'order_process';
                if (!$wasFinalizeStepAvailable && in_array('process', $order->getAvailableSteps())) {
                    $redirectRoute = 'order_finalize';
                }
                return $this->redirectToRoute($redirectRoute, [
                    'participantId' => $participantId,
                    'orderId' => $orderId
                ]);
            }
            $processForm->addError(new FormError('Please correct the errors below'));
        }
        return $this->render('order/process.html.twig', [
            'participant' => $this->orderService->getParticipant(),
            'order' => $order,
            'processForm' => $processForm->createView(),
            'samplesInfo' => $order->getSamplesInformation(),
            'version' => $order->getVersion(),
            'processTabClass' => $order->getProcessTabClass(),
            'revertForm' => $this->createForm(OrderRevertType::class, null)->createView(),
            'readOnlyView' => $this->isReadOnly(),
            'isPediatricOrder' => $order->isPediatricOrder(),
            'inactiveSiteFormDisabled' => $this->orderService->inactiveSiteFormDisabled()
        ]);
    }

    // @ToDo - Replace SessionInterface with Symfony\Component\HttpFoundation\Session
    #[Route(path: '/ppsc/participant/{participantId}/order/{orderId}/finalize', name: 'order_finalize')]
    #[Route(path: '/read/participant/{participantId}/order/{orderId}/finalize', name: 'read_order_finalize', methods: ['GET'])]
    public function orderFinalizeAction($participantId, $orderId, Request $request, Session $session)
    {
        $order = $this->loadOrder($participantId, $orderId);
        $wasPrintRequisitionStepAvailable = in_array('print_requisition', $order->getAvailableSteps());
        if (!in_array('finalize', $order->getAvailableSteps())) {
            return $this->redirectToRoute('order', [
                'participantId' => $participantId,
                'orderId' => $orderId
            ]);
        }
        $formData = $this->orderService->getOrderFormData('finalized');
        $finalizeForm = $this->createForm(OrderType::class, $formData, [
            'step' => 'finalized',
            'order' => $order,
            'em' => $this->em,
            'timeZone' => $this->getSecurityUser()->getTimezone(),
            'siteId' => $this->siteService->getSiteId(),
            'disabled' => $this->isReadOnly() || $this->orderService->inactiveSiteFormDisabled()
        ]);
        $finalizeForm->handleRequest($request);
        if ($finalizeForm->isSubmitted()) {
            if ($order->isDisabled()) {
                throw $this->createAccessDeniedException();
            }
            if (!$order->isFormDisabled()) {
                // Check empty samples
                if (empty($finalizeForm['finalizedSamples']->getData())) {
                    $finalizeForm['finalizedSamples']->addError(new FormError('Please select at least one sample'));
                }
                $errors = $order->getErrors();
                // Check sample errors
                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        $finalizeForm['finalizedSamples']->addError(new FormError($error));
                    }
                }
                // Check identifiers in notes
                if ($type = $this->orderService->getParticipant()->checkIdentifiers($finalizeForm['finalizedNotes']->getData())) {
                    $label = Order::$identifierLabel[$type[0]];
                    $finalizeForm['finalizedNotes']->addError(new FormError("Please remove participant $label \"$type[1]\""));
                }
                if ($order->getType() === Order::ORDER_TYPE_KIT || $order->getType() === Order::ORDER_TYPE_DIVERSION) {
                    if ($finalizeForm->has('sampleShippingMethod')) {
                        if ($finalizeForm['sampleShippingMethod']->getData() === 'fedex' && empty($finalizeForm['fedexTracking']->getData())) {
                            $finalizeForm['fedexTracking']['first']->addError(new FormError('Tracking number required.'));
                        }
                    }

                    if ($finalizeForm->has('fedexTracking') && !empty($finalizeForm['fedexTracking']->getData())) {
                        $duplicateFedexTracking = $this->em->getRepository(Order::class)->getDuplicateFedexTracking(
                            $finalizeForm['fedexTracking']->getData(),
                            $orderId
                        );
                        if (!empty($duplicateFedexTracking)) {
                            $finalizeForm['fedexTracking']['first']->addError(new FormError('This tracking number has already been used for another order.'));
                        }
                    }
                }
                if ($finalizeForm->isValid()) {
                    $this->orderService->setOrderUpdateFromForm('finalized', $finalizeForm);
                    if (!$order->isUnlocked()) {
                        $order->setFinalizedUser($this->getUserEntity());
                        $order->setFinalizedSite($this->siteService->getSiteId());
                    }
                    // Unset finalized_ts for all types of orders
                    $order->setFinalizedTs(null);
                    // Finalized time will not be saved at this point
                    $this->em->persist($order);
                    $this->em->flush();
                    $this->loggerService->log(Log::ORDER_EDIT, $orderId);
                    if (!empty($finalizeForm['finalizedTs']->getData()) || $order->isUnlocked()) {
                        //Send order to mayo if mayo id is empty
                        if (empty($order->getMayoId())) {
                            $site = $this->em->getRepository(Site::class)->findOneBy([
                                'deleted' => 0,
                                'googleGroup' => $this->siteService->getSiteId()
                            ]);
                            $mayoClientId = $site->getMayolinkAccount() ?: null;
                            $result = $this->orderService->sendOrderToMayo($mayoClientId);
                            if ($result['status'] === 'success' && !empty($result['mayoId'])) {
                                //Save mayo id and finalized time
                                $order->setFinalizedTs($finalizeForm['finalizedTs']->getData());
                                $order->setMayoId($result['mayoId']);
                                $this->em->persist($order);
                                $this->em->flush();
                            } else {
                                $this->addFlash('error', $result['errorMessage']);
                            }
                        } else {
                            // Save finalized time
                            $order->setFinalizedTs($finalizeForm['finalizedTs']->getData());
                            $this->em->persist($order);
                            $this->em->flush();
                        }
                    }
                    $sendToRdr = true;
                } else {
                    $finalizeForm->addError(new FormError('Please correct the errors below'));
                }
            }
            if (!empty($sendToRdr) || $order->isFormDisabled()) {
                //Send order to RDR if finalized_ts and mayo_id exists
                if (!empty($order->getFinalizedTs()) && !empty($order->getMayoId())) {
                    if ($this->orderService->sendToRdr()) {
                        $this->addFlash('success', 'Order finalized');
                    } else {
                        $this->addFlash('error', 'Failed to finalize the order. Please try again.');
                    }
                }
                $redirectRoute = 'order_finalize';
                if ($order->getType() !== 'kit' && !$wasPrintRequisitionStepAvailable && (in_array('process', $order->getAvailableSteps()) || $order->isPediatricOrder())) {
                    $redirectRoute = 'order_print_requisition';
                }
                return $this->redirectToRoute($redirectRoute, [
                    'participantId' => $participantId,
                    'orderId' => $orderId
                ]);
            }
        }
        $hasErrors = !empty($order->getErrors()) ? true : false;
        $showUnfinalizeMsg = empty($order->getFinalizedTs()) && !empty($order->getFinalizedSamples()) && empty($session->getFlashBag()->peekAll());
        return $this->render('order/finalize.html.twig', [
            'participant' => $this->orderService->getParticipant(),
            'order' => $order,
            'finalizeForm' => $finalizeForm->createView(),
            'samplesInfo' => $order->getSamplesInformation(),
            'version' => $order->getVersion(),
            'hasErrors' => $hasErrors,
            'processTabClass' => $order->getProcessTabClass(),
            'revertForm' => $this->createForm(OrderRevertType::class, null)->createView(),
            'showUnfinalizeMsg' => $showUnfinalizeMsg,
            'readOnlyView' => $this->isReadOnly(),
            'inactiveSiteFormDisabled' => $this->orderService->inactiveSiteFormDisabled(),
            'isPediatricOrder' => $order->isPediatricOrder(),
        ]);
    }

    #[Route(path: '/ppsc/participant/{participantId}/order/{orderId}/print/requisition', name: 'order_print_requisition')]
    #[Route(path: '/read/participant/{participantId}/order/{orderId}/print/requisition', name: 'read_order_print_requisition')]
    public function orderPrintRequisitionAction($participantId, $orderId, SessionInterface $session)
    {
        $order = $this->loadOrder($participantId, $orderId);
        if ($order->isCancelled()) {
            throw $this->createAccessDeniedException();
        }
        if ($session->get('siteType') == 'dv' && !in_array('print_requisition', $order->getAvailableSteps())) {
            // 404 because print is not a valid route for kit orders regardless of state
            throw $this->createNotFoundException();
        }
        if (!in_array('print_requisition', $order->getAvailableSteps())) {
            return $this->redirectToRoute('order', [
                'participantId' => $participantId,
                'orderId' => $orderId,
                'isPediatricOrder' => $order->isPediatricOrder()
            ]);
        }

        return $this->render('order/print-requisition.html.twig', [
            'participant' => $this->orderService->getParticipant(),
            'order' => $order,
            'processTabClass' => $order->getProcessTabClass(),
            'readOnlyView' => $this->isReadOnly(),
            'isPediatricOrder' => $order->isPediatricOrder()
        ]);
    }

    #[Route(path: '/ppsc/participant/{participantId}/order/{orderId}/requisition/pdf', name: 'order_requisition_pdf')]
    #[Route(path: '/read/participant/{participantId}/order/{orderId}/requisition/pdf', name: 'read_order_requisition_pdf')]
    public function orderRequisitionPdfAction($participantId, $orderId, Request $request, ParameterBagInterface $params)
    {
        $order = $this->loadOrder($participantId, $orderId);
        if (empty($order->getFinalizedTs()) || empty($order->getMayoId()) || $order->isCancelled() || $order->isUnlocked()) {
            throw $this->createAccessDeniedException();
        }
        if (!in_array('print_requisition', $order->getAvailableSteps())) {
            throw $this->createNotFoundException();
        }
        if ($params->has('ml_mock_order')) {
            return $this->redirect($request->getBaseUrl() . '/assets/SampleRequisition.pdf');
        }
        $pdf = $this->orderService->getRequisitionPdf();
        if (!empty($pdf)) {
            return new Response($pdf, 200, ['Content-Type' => 'application/pdf']);
        }
        $html = '<html><body style="font-family: Helvetica Neue,Helvetica,Arial,sans-serif"><strong>Requisition pdf file could not be loaded</strong></body></html>';
        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    #[Route(path: '/ppsc/participant/{participantId}/order/{orderId}/modify/{type}', name: 'order_modify')]
    public function orderModifyAction($participantId, $orderId, $type, Request $request)
    {
        $order = $this->loadOrder($participantId, $orderId);
        // Allow cancel for active and restored orders
        // Allow restore for only canceled orders
        // Allow unlock for active, restored and edited orders
        if (!in_array($type, [$order::ORDER_CANCEL, $order::ORDER_RESTORE, $order::ORDER_UNLOCK])) {
            throw $this->createNotFoundException();
        }
        if (($type === $order::ORDER_CANCEL && !$order->canCancel())
            || ($type === $order::ORDER_RESTORE && !$order->canRestore())
            || ($type === $order::ORDER_UNLOCK && !$order->canUnlock())) {
            throw $this->createAccessDeniedException();
        }
        $orders = $this->em->getRepository(Order::class)->findBy(['participantId' => $participantId], ['id' => 'desc']);
        $orderModifyForm = $this->get('form.factory')->createNamed('form', OrderModifyType::class, null, [
            'type' => $type,
            'orderType' => $order->getType()
        ]);
        $orderModifyForm->handleRequest($request);
        if ($orderModifyForm->isSubmitted()) {
            $orderModifyData = $orderModifyForm->getData();
            if ($orderModifyData['reason'] === 'OTHER' && empty($orderModifyData['other_text'])) {
                $orderModifyForm['other_text']->addError(new FormError('Please enter a reason'));
            }
            if ($type === $order::ORDER_CANCEL && strtolower($orderModifyData['confirm']) !== $order::ORDER_CANCEL) {
                $orderModifyForm['confirm']->addError(new FormError('Please type the word "CANCEL" to confirm'));
            }
            if ($orderModifyForm->isValid()) {
                if ($orderModifyData['reason'] === 'OTHER') {
                    $orderModifyData['reason'] = $orderModifyData['other_text'];
                }
                $status = true;
                // Cancel/Restore order in RDR if exists
                if (!empty($order->getRdrId()) && ($type === $order::ORDER_CANCEL || $type === $order::ORDER_RESTORE)) {
                    $status = $this->orderService->cancelRestoreRdrOrder($type, $orderModifyData['reason']);
                }
                // Create order history
                if ($status && $this->orderService->createOrderHistory($type, $orderModifyData['reason'])) {
                    $successText = 'unlocked';
                    if ($type === $order::ORDER_CANCEL) {
                        $successText = 'cancelled';
                    } elseif ($type === $order::ORDER_RESTORE) {
                        $successText = 'restored';
                    }
                    $this->addFlash('success', "Order {$successText}");
                    if ($type === $order::ORDER_UNLOCK && $request->query->has('return') && preg_match('/^\/\w/', $request->query->get('return'))) {
                        return $this->redirect($request->query->get('return'));
                    }
                    return $this->redirectToRoute('participant', [
                        'id' => $participantId
                    ]);
                }
                $this->addFlash('error', "Failed to {$type} order. Please try again.");
            } else {
                $this->addFlash('error', 'Please correct the errors below');
            }
        }
        return $this->render('order/modify.html.twig', [
            'participant' => $this->orderService->getParticipant(),
            'order' => $order,
            'samplesInfo' => $this->orderService->getCustomSamplesInfo(),
            'orders' => $orders,
            'orderId' => $orderId,
            'sampleModifyForm' => $orderModifyForm->createView(),
            'type' => $type
        ]);
    }

    #[Route(path: '/ppsc/participant/{participantId}/order/{orderId}/revert', name: 'order_revert')]
    public function orderRevertAction($participantId, $orderId, Request $request)
    {
        $order = $this->loadOrder($participantId, $orderId);
        if ($order->isDisabled() || !$order->isUnlocked()) {
            throw $this->createAccessDeniedException();
        }
        $orderRevertForm = $this->createForm(OrderRevertType::class, null);
        $orderRevertForm->handleRequest($request);
        if ($orderRevertForm->isSubmitted() && $orderRevertForm->isValid()) {
            // Revert Order
            if ($this->orderService->revertOrder()) {
                $this->addFlash('notice', 'Order reverted');
            } else {
                $this->addFlash('error', 'Failed to revert order. Please try again.');
            }
        }
        return $this->redirectToRoute('order', [
            'participantId' => $participantId,
            'orderId' => $orderId
        ]);
    }

    #[Route(path: '/ppsc/participant/{participantId}/order/{orderId}/json-response', name: 'order_json')]
    public function orderJsonAction($participantId, $orderId, Request $request, EnvironmentService $env)
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$env->isLocal()) {
            throw $this->createAccessDeniedException();
        }
        $order = $this->loadOrder($participantId, $orderId);
        if ($request->query->has('rdr')) {
            if ($order->getRdrId()) {
                $object = $this->orderService->getOrder($participantId, $order->getRdrId());
            } else {
                $object = ['error' => 'Order does not have rdr_id'];
            }
        } else {
            $object = $order->getRdrObject();
        }
        $response = new JsonResponse($object);
        $response->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_PRETTY_PRINT);
        return $response;
    }

    #[Route(path: '/ppsc/participant/{participantId}/order/{orderId}', name: 'order')]
    #[Route(path: '/read/participant/{participantId}/order/{orderId}', name: 'read_order')]
    public function orderAction($participantId, $orderId)
    {
        $orderRepository = $this->em->getRepository(Order::class);
        $order = $orderRepository->find($orderId);
        if (empty($order)) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $this->orderService->loadSamplesSchema($order);
        $action = $order->getCurrentStep();
        $redirectRoute = $this->isReadOnly() ? 'read_order_' : 'order_';
        return $this->redirectToRoute($redirectRoute . $action, [
            'participantId' => $participantId,
            'orderId' => $orderId
        ]);
    }

    #[Route(path: '/ppsc/participant/{participantId}/order/{orderId}/biobank/summary', name: 'biobank_summary')]
    #[Route(path: '/read/participant/{participantId}/order/{orderId}/biobank/summary', name: 'read_biobank_summary')]
    public function biobankSummaryAction($participantId, $orderId)
    {
        $order = $this->loadOrder($participantId, $orderId);
        return $this->render('biobank/summary.html.twig', [
            'biobankChanges' => $order->getBiobankChangesDetails($this->getSecurityUser()->getTimezone())
        ]);
    }

    #[Route(path: '/ppsc/participant/{participantId}/order/pediatric/check', name: 'order_check_pediatric')]
    public function orderCheckPediatric($participantId, RequestStack $requestStack, MeasurementService $measurementService): Response
    {
        $participant = $this->participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        if (!$participant->status || $this->siteService->isTestSite() || $participant->activityStatus === 'deactivated') {
            throw $this->createAccessDeniedException('Participant ineligible for order create.');
        }
        $measurement = $this->em->getRepository(Measurement::class)->getMostRecentFinalizedNonNullWeight($participant->id);
        if ($measurement) {
            $measurementService->load($measurement, $participant);
            $measurementData = $measurement->getSummary();
        } else {
            $measurementData = null;
        }
        return $this->render('order/check-pediatric.html.twig', [
            'participant' => $participant,
            'siteType' => $requestStack->getSession()->get('siteType'),
            'weightMeasurement' => $measurement,
            'measurementData' => $measurementData,
            'measurementId' => $measurement ? $measurement->getId() : null,
        ]);
    }

    private function createOrderCollectForm(Order $order, array $formData, Request $request, Session $session, ParameterBagInterface $params, string $step): FormInterface
    {
        return $this->createForm(OrderType::class, $formData, [
            'step' => $step,
            'order' => $order,
            'em' => $this->em,
            'timeZone' => $this->getSecurityUser()->getTimezone(),
            'siteId' => $this->siteService->getSiteId(),
            'disabled' => $this->isReadOnly() || $this->orderService->inactiveSiteFormDisabled(),
            'dvSite' => $session->get('siteType') == 'dv',
            'params' => $params
        ]);
    }
}
