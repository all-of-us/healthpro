<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Form\OrderCreateType;
use App\Form\OrderType;
use App\Service\LoggerService;
use App\Service\OrderService;
use App\Service\ParticipantSummaryService;
use App\Service\SiteService;
use Doctrine\ORM\EntityManagerInterface;
use Pmi\Audit\Log;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;

/**
 * @Route("/s")
 */
class OrderController extends AbstractController
{

    protected $em;
    protected $orderService;
    protected $participantSummaryService;
    protected $loggerService;
    protected $siteService;

    public function __construct(
        EntityManagerInterface $em,
        OrderService $orderService,
        ParticipantSummaryService $participantSummaryService,
        LoggerService $loggerService,
        SiteService $siteService
    ) {
        $this->em = $em;
        $this->orderService = $orderService;
        $this->participantSummaryService = $participantSummaryService;
        $this->loggerService = $loggerService;
        $this->siteService = $siteService;
    }

    public function loadOrder($participantId, $orderId)
    {
        $participant = $this->participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $orderRepository = $this->em->getRepository(Order::class);
        $order = $orderRepository->find($orderId);
        if (empty($order)) {
            throw $this->createNotFoundException('Order not found.');
        }
        $this->orderService->loadSamplesSchema($order);
        return [$participant, $order];
    }

    /**
     * @Route("/participant/{participantId}/{orderId}/order", name="order")
     */
    public function orderAction($participantId, $orderId)
    {
        $orderRepository = $this->em->getRepository(Order::class);
        $order = $orderRepository->find($orderId);
        if (empty($order)) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $this->orderService->loadSamplesSchema($order);
        $action = $order->getCurrentStep();
        return $this->redirectToRoute("order_{$action}", [
            'participantId' => $participantId,
            'orderId' => $orderId
        ]);
    }

    /**
     * @Route("/participant/{participantId}/order/check", name="order_check")
     */
    public function orderCheck($participantId)
    {
        $participant = $this->participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        if (!$participant->status || $this->siteService->isTestSite() || $participant->activityStatus === 'deactivated') {
            throw $this->createAccessDeniedException('Participant ineligible for order create.');
        }
        return $this->render('order/check.html.twig', [
            'participant' => $participant
        ]);
    }

    /**
     * @Route("/participant/{participantId}/order/create", name="order_create")
     */
    public function orderCreateAction($participantId, Request $request, SessionInterface $session)
    {
        $participant = $this->participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        if (!$participant->status || $this->siteService->isTestSite() || ($session->get('siteType') === 'dv' && $request->request->has('saliva')) || $participant->activityStatus === 'deactivated') {
            throw $this->createAccessDeniedException('Participant ineligible for order create.');
        }
        if ($request->request->has('show-blood-tubes') && $request->request->has('show-saliva-tubes')) {
            $showBloodTubes = $request->request->get('show-blood-tubes') === 'yes' ? true : false;
            $showSalivaTubes = $request->request->get('show-saliva-tubes') === 'yes' ? true : false;
        } else {
            throw $this->createAccessDeniedException('Participant ineligible for order create.');
        }
        $order = new Order;
        $this->orderService->loadSamplesSchema($order);
        $createForm = $this->createForm(OrderCreateType::class, null, [
            'orderType' => $session->get('orderType'),
            'samples' => $order->getSamples(),
            'showBloodTubes' => $showBloodTubes,
            'nonBloodSamples' => $order::$nonBloodSamples
        ]);
        $showCustom = false;
        $createForm->handleRequest($request);
        if (!$createForm->isSubmitted() && !$this->get('security.csrf.token_manager')->isTokenValid(new CsrfToken('orderCheck',
                $request->request->get('csrf_token')))) {
            throw $this->createAccessDeniedException('Participant ineligible for order create.');
        }
        if ($createForm->isSubmitted() && $createForm->isValid()) {
            if ($request->request->has('existing')) {
                $orderRepository = $this->em->getRepository(Order::class);
                if (empty($createForm['kitId']->getData())) {
                    $createForm['kitId']['first']->addError(new FormError('Please enter a kit order ID'));
                } elseif ($orderRepository->findOneBy(['order_id' => $createForm['kitId']->getData()])) {
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
            }
            if ($createForm->isValid()) {
                $userRepository = $this->em->getRepository(User::class);
                $order->setUser($userRepository->find($this->getUser()->getId()));
                $order->setSite($this->siteService->getSiteId());
                $order->setParticipantId($participant->id);
                $order->setBiobankId($participant->biobankId);
                $order->setCreatedTs(new \DateTime());
                $order->setVersion($order->getCurrentVersion());
                if ($session->get('orderType') === 'hpo') {
                    $order->setProcessedCentrifugeType(Order::SWINGING_BUCKET);
                }
                if ($session->get('siteType') === 'dv' && $this->siteService->isDiversionPouchSite()) {
                    $order->setType('diversion');
                }
                $order->setOrderId($this->orderService->generateId());
                $this->em->persist($order);
                $this->em->flush();
                $orderId = $order->getId();
                if ($orderId) {
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
        ]);
    }

    /**
     * @Route("/participant/{participantId}/order/{orderId}/print/labels", name="order_print_labels")
     */
    public function orderPrintLabelsAction($participantId, $orderId)
    {
        list($participant, $order) = $this->loadOrder($participantId, $orderId);
        if ($order->isDisabled() || $order->isUnlocked()) {
            throw $this->createAccessDeniedException('Participant ineligible for order create.');
        }
        if (!in_array('print_labels', $order->getAvailableSteps())) {
            // 404 because print is not a valid route for kit orders regardless of state
            throw $this->createAccessDeniedException('Participant ineligible for order create.');
        }
        $result = $this->orderService->getLabelsPdf($participant);
        if (!$order->getPrintedTs() && $result['status'] === 'success') {
            $order->setPrintedTs(new \DateTime());
            $this->em->persist($order);
            $this->em->flush();
            $this->loggerService->log(Log::ORDER_EDIT, $orderId);
        }
        $errorMessage = !empty($result['errorMessage']) ? $result['errorMessage'] : '';
        return $this->render('order/print-labels.html.twig', [
            'participant' => $participant,
            'order' => $order,
            'processTabClass' => $order->getProcessTabClass(),
            'errorMessage' => $errorMessage
        ]);
    }

    /**
     * @Route("/participant/{participantId}/order/{orderId}/labels/pdf", name="order_labels_pdf")
     */
    public function orderLabelsPdfAction($participantId, $orderId, Request $request, ParameterBagInterface $params)
    {
        list($participant, $order) = $this->loadOrder($participantId, $orderId);
        if ($order->isDisabled() || $order->isUnlocked()) {
            throw $this->createAccessDeniedException('Participant ineligible for order create.');
        }
        if (!in_array('print_labels', $order->getAvailableSteps())) {
            // 404 because print is not a valid route for kit orders regardless of state
            throw $this->createAccessDeniedException('Participant ineligible for order create.');
        }
        if ($params->has('ml_mock_order')) {
            return $this->redirect($request->getBaseUrl() . '/assets/SampleLabels.pdf');
        } else {
            $result = $this->orderService->getLabelsPdf($participant);
            if ($result['status'] === 'success') {
                return new Response($result['pdf'], 200, ['Content-Type' => 'application/pdf']);
            } else {
                $html = '<html><body style="font-family: Helvetica Neue,Helvetica,Arial,sans-serif"><strong>' . $result['errorMessage'] . '</strong></body></html>';
                return new Response($html, 200, ['Content-Type' => 'text/html']);
            }
        }
    }

    /**
     * @Route("/participant/{participantId}/order/{orderId}/collect", name="order_collect")
     */
    public function orderCollectAction($participantId, $orderId, Request $request)
    {
        list($participant, $order) = $this->loadOrder($participantId, $orderId);
        if (!in_array('collect', $order->getAvailableSteps())) {
            return $this->redirectToRoute('order', [
                'participantId' => $participantId,
                'orderId' => $orderId
            ]);
        }
        $formData = $this->orderService->getOrderFormData('collected');
        $collectForm = $this->createForm(OrderType::class, $formData, [
            'step' => 'collected',
            'order' => $order,
            'em' => $this->em,
            'timeZone' => $this->getUser()->getInfo()['timezone'],
            'siteId' => $this->siteService->getSiteId()
        ]);
        $collectForm->handleRequest($request);
        if ($collectForm->isSubmitted()) {
            if ($order->isDisabled()) {
                throw $this->createAccessDeniedException('Participant ineligible for order create.');
            }
            if ($type = $participant->checkIdentifiers($collectForm['collectedNotes']->getData())) {
                $label = Order::$identifierLabel[$type[0]];
                $collectForm['collectedNotes']->addError(new FormError("Please remove participant $label \"$type[1]\""));
            }
            if ($collectForm->isValid()) {
                $this->orderService->getOrderUpdateFromForm('collected', $collectForm);
                if (!$order->isUnlocked()) {
                    $userRepository = $this->em->getRepository(User::class);
                    $order->setCollectedUser($userRepository->find($this->getUser()->getId()));
                    $order->setCollectedSite($this->siteService->getSiteId());
                }
                // Save order
                $this->em->persist($order);
                $this->em->flush();
                $this->loggerService->log(Log::ORDER_EDIT, $orderId);
                $this->addFlash('notice', 'Order collection updated');
                return $this->redirectToRoute('order_collect', [
                    'participantId' => $participantId,
                    'orderId' => $orderId
                ]);
            } else {
                $collectForm->addError(new FormError('Please correct the errors below'));
            }
        }
        return $this->render('order/collect.html.twig', [
            'participant' => $participant,
            'order' => $order,
            'collectForm' => $collectForm->createView(),
            'samplesInfo' => $order->getSamplesInformation(),
            'version' => $order->getVersion(),
            'processTabClass' => $order->getProcessTabClass(),
//            'revertForm' => $order->getOrderRevertForm()->createView()
        ]);
    }

    /**
     * @Route("/participant/{participantId}/order/{orderId}/process", name="order_process")
     */
    public function orderProcessAction($participantId, $orderId)
    {
        //Todo
        return '';
    }

    /**
     * @Route("/participant/{participantId}/order/{orderId}/finalize", name="order_finalize")
     */
    public function orderFinalizeAction($participantId, $orderId)
    {
        //Todo
        return '';
    }

    /**
     * @Route("/participant/{participantId}/order/{orderId}/modify/{type}", name="order_modify")
     */
    public function orderModifyAction($participantId, $orderId, $type)
    {
        //Todo
        return '';
    }
}
