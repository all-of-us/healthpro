<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Site;
use App\Form\BiobankOrderType;
use App\Form\OrderLookupType;
use App\Form\ParticipantLookupBiobankIdType;
use App\Service\BiobankOrderFinalizeNotificationService;
use App\Service\EnvironmentService;
use App\Service\LoggerService;
use App\Service\OrderService;
use App\Service\ParticipantSummaryService;
use App\Service\ReviewService;
use Doctrine\ORM\EntityManagerInterface;
use Pmi\Audit\Log;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s/biobank")
 */
class BiobankController extends AbstractController
{
    protected $em;
    protected $participantSummaryService;
    protected $orderService;
    protected $loggerService;
    protected $params;

    public function __construct(
        EntityManagerInterface $em,
        ParticipantSummaryService $participantSummaryService,
        OrderService $orderService,
        LoggerService $loggerService,
        ParameterBagInterface $params
    ) {
        $this->em = $em;
        $this->participantSummaryService = $participantSummaryService;
        $this->orderService = $orderService;
        $this->loggerService = $loggerService;
        $this->params = $params;
    }

    /**
     * @Route("/", name="biobank_home")
     */
    public function indexAction(): Response
    {
        return $this->render('biobank/index.html.twig');
    }

    /**
     * @Route("/participants", name="biobank_participants")
     */
    public function participantsAction(Request $request): Response
    {
        $bioBankIdPrefix = $this->params->has('biobank_id_prefix') ? $this->params->get('biobank_id_prefix') : null;
        $idForm = $this->createForm(ParticipantLookupBiobankIdType::class, null, ['bioBankIdPrefix' => $bioBankIdPrefix]);
        $idForm->handleRequest($request);
        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $searchParameters = $idForm->getData();
            $searchResults = $this->participantSummaryService->search($searchParameters);
            if (!empty($searchResults)) {
                return $this->redirectToRoute('biobank_participant', [
                    'biobankId' => $searchResults[0]->biobankId
                ]);
            }
            $this->addFlash('error', 'Biobank ID not found');
        }
        return $this->render('biobank/participants.html.twig', [
            'idForm' => $idForm->createView()
        ]);
    }

    /**
     * @Route("/orders", name="biobank_orders")
     */
    public function ordersAction(Request $request): Response
    {
        $idForm = $this->createForm(OrderLookupType::class, null);
        $idForm->handleRequest($request);
        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $id = $idForm->get('orderId')->getData();

            // New barcodes include a 4-digit sample identifier appended to the 10 digit order id
            // If the string matches this format, remove the sample identifier to get the order id
            if (preg_match('/^\d{14}$/', $id)) {
                $id = substr($id, 0, 10);
            }
            // Internal Order
            $order = $this->em->getRepository(Order::class)->findOneBy([
                'orderId' => $id
            ]);
            if ($order) {
                return $this->redirectToRoute('biobank_order', [
                    'biobankId' => $order->getBiobankId(),
                    'orderId' => $order->getId()
                ]);
            }
            // Quanum Orders
            $order = new Order();
            $this->orderService->loadSamplesSchema($order);
            $quanumOrders = $this->orderService->getOrders([
                'kitId' => $id,
                'origin' => 'careevolution'
            ]);
            if (isset($quanumOrders[0])) {
                $order = $this->orderService->loadFromJsonObject($quanumOrders[0]);
                $participant = $this->participantSummaryService->getParticipantById($order->getParticipantId());
                if ($participant->biobankId) {
                    return $this->redirectToRoute('biobank_quanum_order', [
                        'biobankId' => $participant->biobankId,
                        'orderId' => $order->getRdrId()
                    ]);
                }
            }
            $this->addFlash('error', 'Order ID not found');
        }

        return $this->render('biobank/orders.html.twig', [
            'idForm' => $idForm->createView()
        ]);
    }

    /**
     * @Route("/{biobankId}/order/{orderId}", name="biobank_order")
     */
    public function orderAction(string $biobankId, int $orderId, Request $request, BiobankOrderFinalizeNotificationService $biobankOrderFinalizeNotificationService): Response
    {
        $participant = $this->participantSummaryService->search(['biobankId' => $biobankId]);
        if (empty($participant)) {
            throw $this->createNotFoundException();
        }
        $participant = $participant[0];
        if (!($participant->status || $participant->editExistingOnly)) {
            throw $this->createAccessDeniedException();
        }
        $order = $this->em->getRepository(Order::class)->find($orderId);
        if (empty($order)) {
            throw $this->createNotFoundException();
        }
        $this->orderService->loadSamplesSchema($order);
        $this->orderService->setParticipant($participant);

        // Available steps
        $steps = ['collect', 'process', 'finalize'];
        // Set default step if current step not exists in the available steps
        $currentStep = !in_array($order->getCurrentStep(), $steps) ? 'collect' : $order->getCurrentStep();
        $site = $this->em->getRepository(Site::class)->findOneBy([
            'deleted' => 0,
            'googleGroup' => $order->getSite()
        ]);
        $siteCentrifugeType = !empty($site) ? $site->getCentrifugeType() : null;
        $finalizeForm = $this->createForm(BiobankOrderType::class, null, ['order' => $order, 'siteCentrifugeType' => $siteCentrifugeType]);
        $finalizeForm->handleRequest($request);
        if ($finalizeForm->isSubmitted()) {
            if ($order->isDisabled() || !$this->isGranted('ROLE_BIOBANK')) {
                throw $this->createAccessDeniedException();
            }
            if (!$order->isFormDisabled()) {
                // Check empty samples
                if (empty($finalizeForm['finalizedSamples']->getData())) {
                    $finalizeForm['finalizedSamples']->addError(new FormError('Please select at least one sample'));
                }
                // Check identifiers in notes
                if ($type = $participant->checkIdentifiers($finalizeForm['finalizedNotes']->getData())) {
                    $label = Order::$identifierLabel[$type[0]];
                    $finalizeForm['finalizedNotes']->addError(new FormError("Please remove participant $label \"$type[1]\""));
                }
                // Check if centrifuge type is required or not
                if ($finalizeForm->has('processedCentrifugeType') && empty($finalizeForm['processedCentrifugeType']->getData()) && !empty($finalizeForm['finalizedSamples']->getData()) && $order->requireCentrifugeType($finalizeForm['finalizedSamples']->getData())) {
                    $finalizeForm['processedCentrifugeType']->addError(new FormError('Please select centrifuge type'));
                }
                if ($finalizeForm->isValid()) {
                    //Send order to mayo if mayo id is empty
                    if (empty($order->getMayoId())) {
                        $finalizedTs = new \DateTime();
                        $samples = [];
                        if ($finalizeForm["finalizedSamples"]->getData() && is_array($finalizeForm["finalizedSamples"]->getData())) {
                            $samples = array_values($finalizeForm["finalizedSamples"]->getData());
                        }
                        // Check centrifuge type for dv kit orders
                        $centrifugeType = null;
                        if ($order->getType() === 'kit' && empty($order->getProcessedCentrifugeType())) {
                            if ($finalizeForm->has('processedCentrifugeType')) {
                                $centrifugeType = $finalizeForm['processedCentrifugeType']->getData();
                            } elseif (!empty($site['centrifugeType'])) {
                                $centrifugeType = $site['centrifugeType'];
                            }
                            if (!empty($centrifugeType)) {
                                $order->setProcessedCentrifugeType($centrifugeType);
                            }
                        }
                        // Set collected ts and finalized samples that are needed to send order to mayo
                        $collectedTs = $order->getCollectedTs();
                        if (empty($collectedTs)) {
                            $order->setCollectedTs($finalizedTs->setTimezone(new \DateTimeZone($this->getUser()->getTimezone())));
                        }
                        $order->setFinalizedSamples(json_encode($samples));

                        $mayoClientId = $site->getMayolinkAccount() ?: null;
                        $result = $this->orderService->sendOrderToMayo($mayoClientId);
                        if ($result['status'] === 'success' && !empty($result['mayoId'])) {
                            // Check biobank changes
                            $order->checkBiobankChanges(
                                $collectedTs,
                                $finalizedTs,
                                $samples,
                                $finalizeForm['finalizedNotes']->getData(),
                                $centrifugeType
                            );
                            // Save mayo id
                            $order->setMayoId($result['mayoId']);
                            $this->em->persist($order);
                            $this->em->flush();
                            $this->loggerService->log(Log::ORDER_EDIT, $orderId);

                            // Send email to site user
                            $info = [
                                'participantId' => $participant->id,
                                'biobankId' => $biobankId,
                                'orderId' => $order->getOrderId()
                            ];
                            $emails = !empty($site) ? $site->getEmail() : null;
                            $biobankOrderFinalizeNotificationService->sendEmails($info, $emails);
                        } else {
                            $this->addFlash('error', $result['errorMessage']);
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
                return $this->redirectToRoute('biobank_order', [
                    'biobankId' => $biobankId,
                    'orderId' => $orderId
                ]);
            }
        }
        // Set processed ts to null if processed samples ts are empty
        if (empty(json_decode($order->getProcessedSamplesTs()))) {
            $order->setProcessedTs(null);
        }
        // Using collected samples to compare with finalized samples since users can't check processed samples that are not collected
        $collectedSamples = json_decode($order->getCollectedSamples(), true);
        return $this->render('biobank/order.html.twig', [
            'participant' => $participant,
            'order' => $order,
            'samplesInfoText' => $this->orderService->getCustomSamplesInfo(),
            'currentStep' => $currentStep,
            'finalizeForm' => $finalizeForm->createView(),
            'samplesInfo' => $order->getSamplesInformation(),
            'version' => $order->getVersion(),
            'collectedSamples' => $collectedSamples ?: null
        ]);
    }

    /**
     * @Route("/{biobankId}/quanum-order/{orderId}", name="biobank_quanum_order")
     */
    public function quanumOrderAction(string $biobankId, string $orderId): Response
    {
        $participant = $this->participantSummaryService->search(['biobankId' => $biobankId]);
        if (!$participant) {
            throw $this->createNotFoundException(404);
        }
        $participant = $participant[0];

        $order = new Order();
        $this->orderService->loadSamplesSchema($order);
        $quanumOrder = $this->orderService->getOrder($participant->id, $orderId);
        $order = $this->orderService->loadFromJsonObject($quanumOrder);
        return $this->render('biobank/order-quanum.html.twig', [
            'participant' => $participant,
            'samplesInfoText' => $this->orderService->getCustomSamplesInfo(),
            'currentStep' => 'finalize',
            'order' => $order
        ]);
    }

    /**
     * @Route("/review/orders/today", name="biobank_orders_today")
     */
    public function ordersTodayAction(Request $request, EnvironmentService $env, ReviewService $reviewService): Response
    {
        // Get beginning of today (at midnight) in user's timezone
        $startString = 'today';
        // Allow overriding start time to test in non-prod environments
        if (!$env->isProd() && intval($request->query->get('days')) > 0) {
            $startString = '-' . intval($request->query->get('days')) . ' days';
        }
        $startTime = new \DateTime($startString, new \DateTimeZone($this->getUser()->getTimezone()));
        // Get MySQL date/time string in UTC
        $startTime->setTimezone(new \DateTimezone('UTC'));
        $today = $startTime->format('Y-m-d H:i:s');

        $orders = $reviewService->getTodayOrders($today);

        return $this->render('biobank/orders-today.html.twig', [
            'orders' => $orders
        ]);
    }

    /**
     * @Route("/review/quanum-orders/today", name="biobank_quanum_orders_today")
     */
    public function quanumOrdersTodayAction(Request $request, EnvironmentService $env): Response
    {
        // Get beginning of today (at midnight) in user's timezone
        $startString = 'today';
        // Allow overriding start time to test in non-prod environments
        if (!$env->isProd() && intval($request->query->get('days')) > 0) {
            $startString = '-' . intval($request->query->get('days')) . ' days';
        }
        $startTime = new \DateTime($startString, new \DateTimeZone($this->getUser()->getTimezone()));
        $today = $startTime->format('Y-m-d');
        $endDate = (new \DateTime('today', new \DateTimezone('UTC')))->format('Y-m-d');

        $quanumOrders = $this->orderService->getOrders([
            'startDate' => $today,
            'endDate' => $endDate,
            'origin' => 'careevolution',
            'page' => '1',
            'pageSize' => '1000'
        ]);
        $orders = [];
        foreach ($quanumOrders as $quanumOrder) {
            if ($quanumOrder->origin === 'careevolution') {
                $order = new Order();
                $this->orderService->loadSamplesSchema($order);
                $orders[] = $this->orderService->loadFromJsonObject($quanumOrder);
            }
        }
        return $this->render('biobank/orders-quanum-today.html.twig', [
            'orders' => $orders
        ]);
    }

    /**
     * @Route("/review/orders/unfinalized", name="biobank_orders_unfinalized")
     */
    public function ordersUnfinalizedAction(): Response
    {
        $unfinalizedOrders = $this->em->getRepository(Order::class)->getUnfinalizedOrders();
        return $this->render('biobank/orders-unfinalized.html.twig', [
            'orders' => $unfinalizedOrders
        ]);
    }

    /**
     * @Route("/review/orders/unlocked", name="biobank_orders_unlocked")
     */
    public function ordersUnlockedAction(): Response
    {
        $unlockedOrders = $this->em->getRepository(Order::class)->getUnlockedOrders();
        return $this->render('biobank/orders-unlocked.html.twig', [
            'orders' => $unlockedOrders
        ]);
    }

    /**
     * @Route("/review/orders/recent/modify", name="biobank_orders_recentModify")
     */
    public function biobankOrdersRecentModifyAction(): Response
    {
        $recentModifyOrders = $this->em->getRepository(Order::class)->getRecentModifiedOrders();
        return $this->render('biobank/orders-recent-modify.html.twig', [
            'orders' => $recentModifyOrders
        ]);
    }

    /**
     * @Route("/{biobankId}", name="biobank_participant")
     */
    public function participantAction(string $biobankId): Response
    {
        $participant = $this->participantSummaryService->search(['biobankId' => $biobankId]);
        if (empty($participant)) {
            throw $this->createNotFoundException();
        }
        $participant = $participant[0];

        // Internal Orders
        $orders = $this->em->getRepository(Order::class)->findBy(['participantId' => $participant->id], ['id' => 'desc']);

        // Quanum Orders
        $quanumOrders = $this->orderService->getOrdersByParticipant($participant->id);

        foreach ($quanumOrders as $quanumOrder) {
            if ($quanumOrder->origin === 'careevolution') {
                $order = new Order();
                $this->orderService->loadSamplesSchema($order);
                $orders[] = $this->orderService->loadFromJsonObject($quanumOrder);
            }
        }
        return $this->render('biobank/participant.html.twig', [
            'participant' => $participant,
            'orders' => $orders,
            'biobankView' => true,
            'canViewOrders' => $participant->status || $participant->editExistingOnly
        ]);
    }
}
