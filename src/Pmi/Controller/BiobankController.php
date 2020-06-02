<?php
namespace Pmi\Controller;

use Pmi\Service\NotifyBiobankOrderFinalizeService;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Form\FormError;
use Pmi\Drc\Exception\ParticipantSearchExceptionInterface;
use Pmi\Order\Order;
use Pmi\Review\Review;
use Pmi\Audit\Log;

class BiobankController extends AbstractController
{
    protected static $name = 'biobank';

    protected static $routes = [
        ['home', '/'],
        ['participants', '/participants', ['method' => 'GET|POST']],
        ['orders', '/orders', ['method' => 'GET|POST']],
        ['participant', '/{biobankId}'],
        ['order', '/{biobankId}/order/{orderId}', ['method' => 'GET|POST']],
        ['quanumOrder', '/{biobankId}/quanum-order/{orderId}'],
        ['ordersToday', '/review/orders/today'],
        ['quanumOrdersToday', '/review/quanum-orders/today'],
        ['ordersUnfinalized', '/review/orders/unfinalized'],
        ['ordersUnlocked', '/review/orders/unlocked'],
        ['ordersRecentModify', '/review/orders/recent/modify']
    ];

    public function homeAction(Application $app)
    {
        return $app['twig']->render('biobank/index.html.twig');
    }

    public function participantsAction(Application $app, Request $request)
    {
        $bioBankIdPrefix = $app->getConfig('biobank_id_prefix');
        $constraints = [
            new Constraints\NotBlank(),
            new Constraints\Type('string')
        ];
        if (!empty($bioBankIdPrefix)) {
            $bioBankIdPrefixQuote = preg_quote($bioBankIdPrefix, '/');
            $constraints[] = new Constraints\Regex([
                'pattern' => "/^{$bioBankIdPrefixQuote}\d+$/",
                'message' => "Invalid biobank ID. Must be in the format of {$bioBankIdPrefix}000000000"
            ]);
        }
        $idForm = $app['form.factory']->createNamedBuilder('id', Type\FormType::class)
            ->add('biobankId', Type\TextType::class, [
                'label' => 'Biobank ID',
                'constraints' => $constraints,
                'attr' => [
                    'placeholder' => !empty($bioBankIdPrefix) ? $bioBankIdPrefix . '000000000' : 'Enter biobank ID'
                ]
            ])
            ->getForm();

        $idForm->handleRequest($request);

        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $searchParameters = $idForm->getData();
            try {
                $searchResults = $app['pmi.drc.participants']->search($searchParameters);
                if (!empty($searchResults)) {
                    return $app->redirectToRoute('biobank_participant', [
                        'biobankId' => $searchResults[0]->biobankId
                    ]);
                }
                $app->addFlashError('Biobank ID not found');
            } catch (ParticipantSearchExceptionInterface $e) {
                $app->addFlashError('Biobank ID not found');
            }
        }

        return $app['twig']->render('biobank/participants.html.twig', [
            'idForm' => $idForm->createView()
        ]);
    }

    public function ordersAction(Application $app, Request $request)
    {
        $idForm = $app['form.factory']->createNamedBuilder('id', Type\FormType::class)
            ->add('orderId', Type\TextType::class, [
                'label' => 'Order ID',
                'attr' => ['placeholder' => 'Scan barcode or enter order ID'],
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ]
            ])
            ->getForm();

        $idForm->handleRequest($request);

        if ($idForm->isSubmitted() && $idForm->isValid()) {
            $id = $idForm->get('orderId')->getData();

            // New barcodes include a 4-digit sample identifier appended to the 10 digit order id
            // If the string matches this format, remove the sample identifier to get the order id
            if (preg_match('/^\d{14}$/', $id)) {
                $id = substr($id, 0, 10);
            }
            // Internal Order
            $order = $app['em']->getRepository('orders')->fetchOneBy([
                'order_id' => $id
            ]);
            if ($order) {
                return $app->redirectToRoute('biobank_order', [
                    'biobankId' => $order['biobank_id'],
                    'orderId' => $order['id']
                ]);
            }
            // Quanum Orders
            $quanumOrders = $app['pmi.drc.participants']->getOrders([
                'kitId' => $id,
                'origin' => 'careevolution'
            ]);
            if (isset($quanumOrders[0])) {
                $order = (new Order($app))->loadFromJsonObject($quanumOrders[0])->toArray();
                $participant = $app['pmi.drc.participants']->getById($order['participant_id']);
                if ($participant->biobankId) {
                    return $app->redirectToRoute('biobank_quanumOrder', [
                        'biobankId' => $participant->biobankId,
                        'orderId' => $order['id']
                    ]);
                }
            }
            $app->addFlashError('Order ID not found');
        }

        return $app['twig']->render('biobank/orders.html.twig', [
            'idForm' => $idForm->createView()
        ]);
    }


    public function participantAction($biobankId, Application $app)
    {
        try {
            $participant = $app['pmi.drc.participants']->search(['biobankId' => $biobankId]);
        } catch (ParticipantSearchExceptionInterface $e) {
            $app->abort(404);
        }

        if (empty($participant)) {
            $app->abort(404);
        }
        $participant = $participant[0];

        // Internal Orders
        $orders = $app['em']->getRepository('orders')->getParticipantOrdersWithHistory($participant->id);

        // Quanum Orders
        $quanumOrders = $app['pmi.drc.participants']->getOrdersByParticipant($participant->id);
        foreach ($quanumOrders as $quanumOrder) {
            if (in_array($quanumOrder->origin, ['careevolution'])) {
                $orders[] = (new Order($app))->loadFromJsonObject($quanumOrder)->toArray();
            }
        }

        return $app['twig']->render('biobank/participant.html.twig', [
            'participant' => $participant,
            'orders' => $orders,
            'cacheEnabled' => $app['pmi.drc.participants']->getCacheEnabled(),
            'biobankView' => true
        ]);
    }

    public function orderAction($biobankId, $orderId, Application $app, Request $request)
    {
        try {
            $participant = $app['pmi.drc.participants']->search(['biobankId' => $biobankId]);
        } catch (ParticipantSearchExceptionInterface $e) {
            $app->abort(404);
        }

        if (!$participant) {
            $app->abort(404);
        }
        $participant = $participant[0];
        $order = new Order($app);
        $order->loadOrder($participant->id, $orderId);
        if (!$order->isValid()) {
            $app->abort(404);
        }
        if (!$order->getParticipant()->status || $app->isTestSite()) {
            $app->abort(403);
        }
        // Available steps
        $steps = ['collect', 'process', 'finalize'];
        // Set default step if current step not exists in the available steps
        $currentStep = !in_array($order->getCurrentStep(), $steps) ? 'collect' : $order->getCurrentStep();
        $finalizeForm = $order->createBiobankOrderFinalizeForm($app['form.factory']);
        $finalizeForm->handleRequest($request);
        if ($finalizeForm->isSubmitted()) {
            if ($order->isOrderDisabled()) {
                $app->abort(403);
            }
            if (!$order->isOrderFormDisabled()) {
                // Check empty samples
                if (empty($finalizeForm['finalized_samples']->getData())) {
                    $finalizeForm['finalized_samples']->addError(new FormError('Please select at least one sample'));
                }
                $errors = $order->getErrors();
                // Check sample errors
                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        $finalizeForm['finalized_samples']->addError(new FormError($error));
                    }
                }
                // Check identifiers in notes
                if ($type = $order->checkIdentifiers($finalizeForm['finalized_notes']->getData())) {
                    $label = Order::$identifierLabel[$type[0]];
                    $finalizeForm['finalized_notes']->addError(new FormError("Please remove participant $label \"$type[1]\""));
                }
                if ($finalizeForm->isValid()) {
                    //Send order to mayo if mayo id is empty
                    if (empty($order->get('mayo_id'))) {
                        $finalizedTs = new \DateTime();
                        $samples = [];
                        if ($finalizeForm["finalized_samples"]->getData() && is_array($finalizeForm["finalized_samples"]->getData())) {
                            $samples = array_values($finalizeForm["finalized_samples"]->getData());
                        }
                        // Check centrifuge type
                        if ($order->get('type') === 'kit' && empty($order->get('processed_centrifuge_type'))) {
                            $site = $app['em']->getRepository('sites')->fetchOneBy([
                                'deleted' => 0,
                                'google_group' => $app->getSiteId()
                            ]);
                            if (!empty($site['centrifuge_type'])) {
                                $order->set('processed_centrifuge_type', $site['centrifuge_type']);
                            }
                        }
                        $order->set('biobank_collected_ts', $order->get('collected_ts') ?: $finalizedTs->setTimezone(new \DateTimeZone($app->getUserTimezone())));
                        $order->set('biobank_finalized_samples', json_encode($samples));
                        $result = $order->sendOrderToMayo();
                        if ($result['status'] === 'success' && !empty($result['mayoId'])) {
                            $updateArray = [];
                            // Check biobank changes
                            $order->checkBiobankChanges($updateArray, $finalizedTs, $samples, $finalizeForm['finalized_notes']->getData());
                            // Save mayo id
                            $updateArray['mayo_id'] = $result['mayoId'];

                            if ($app['em']->getRepository('orders')->update($orderId, $updateArray)) {
                                $app->log(Log::ORDER_EDIT, $orderId);
                            }
                            // Send email to site user
                            $info = [
                                'participantId' => $participant->id,
                                'biobankId' => $biobankId,
                                'orderId' => $order->get('order_id'),
                                'siteId' => $order->get('site')
                            ];
                            $notify = new NotifyBiobankOrderFinalizeService($app);
                            $notify->sendEmails($info);
                        } else {
                            $app->addFlashError($result['errorMessage']);
                        }
                    }
                    $sendToRdr = true;
                } else {
                    $finalizeForm->addError(new FormError('Please correct the errors below'));
                }
            }
            if (!empty($sendToRdr) || $order->isOrderFormDisabled()) {
                $order->loadOrder($participant->id, $orderId);
                //Send order to RDR if finalized_ts and mayo_id exists
                if (!empty($order->get('finalized_ts')) && !empty($order->get('mayo_id'))) {
                    if ($order->sendToRdr()) {
                        $app->addFlashSuccess('Order finalized');
                    } else {
                        $app->addFlashError('Failed to finalize the order. Please try again.');
                    }
                }
                return $app->redirectToRoute('biobank_order', [
                    'biobankId' => $biobankId,
                    'orderId' => $orderId
                ]);
            }
        }
        $hasErrors = !empty($order->getErrors()) ? true : false;
        $orderArray = $order->toArray();
        // Set processed ts to null if processed samples ts are empty
        if (empty(json_decode($order->get('processed_samples_ts')))) {
            $orderArray['processed_ts'] = null;
        }
        // Using collected samples to compare with finalized samples since users can't check processed samples that are not collected
        $collectedSamples = json_decode($order->get('collected_samples'), true);
        return $app['twig']->render('biobank/order.html.twig', [
            'participant' => $participant,
            'order' => $orderArray,
            'samplesInfoText' => $order->getSamplesInfo(),
            'currentStep' => $currentStep,
            'finalizeForm' => $finalizeForm->createView(),
            'samplesInfo' => $order->samplesInformation,
            'version' => $order->version,
            'hasErrors' => $hasErrors,
            'collectedSamples' => $collectedSamples ?: null
        ]);
    }

    public function quanumOrderAction($biobankId, $orderId, Application $app)
    {
        try {
            $participant = $app['pmi.drc.participants']->search(['biobankId' => $biobankId]);
        } catch (ParticipantSearchExceptionInterface $e) {
            $app->abort(404);
        }

        if (!$participant) {
            $app->abort(404);
        }
        $participant = $participant[0];

        $quanumOrder = $app['pmi.drc.participants']->getOrder($participant->id, $orderId);
        $order = (new Order($app))->loadFromJsonObject($quanumOrder);

        return $app['twig']->render('biobank/order-quanum.html.twig', [
            'participant' => $participant,
            'samplesInfo' => $order->getSamplesInfo(),
            'currentStep' => 'finalize',
            'order' => $order->toArray()
        ]);
    }

    public function ordersTodayAction(Application $app, Request $request)
    {
        // Get beginning of today (at midnight) in user's timezone
        $startString = 'today';
        // Allow overriding start time to test in non-prod environments
        if (!$app->isProd() && intval($request->query->get('days')) > 0) {
            $startString = '-' . intval($request->query->get('days')) . ' days';
        }
        $startTime = new \DateTime($startString, new \DateTimeZone($app->getUserTimezone()));
        // Get MySQL date/time string in UTC
        $startTime->setTimezone(new \DateTimezone('UTC'));
        $today = $startTime->format('Y-m-d H:i:s');

        $review = new Review($app['db']);
        $orders = $review->getTodayOrders($today);

        return $app['twig']->render('biobank/orders-today.html.twig', [
            'orders' => $orders
        ]);
    }

    public function quanumOrdersTodayAction(Application $app, Request $request)
    {
        // Get beginning of today (at midnight) in user's timezone
        $startString = 'today';
        // Allow overriding start time to test in non-prod environments
        if (!$app->isProd() && intval($request->query->get('days')) > 0) {
            $startString = '-' . intval($request->query->get('days')) . ' days';
        }
        $startTime = new \DateTime($startString, new \DateTimeZone($app->getUserTimezone()));
        $today = $startTime->format('Y-m-d');
        $endDate = (new \DateTime('today', new \DateTimezone('UTC')))->format('Y-m-d');

        $quanumOrders = $app['pmi.drc.participants']->getOrders([
            'startDate' => $today,
            'endDate' => $endDate,
            'origin' => 'careevolution',
            'page' => '1',
            'pageSize' => '1000'
        ]);
        $orders = [];
        foreach ($quanumOrders as $quanumOrder) {
            if (in_array($quanumOrder->origin, ['careevolution'])) {
                $orders[] = (new Order($app))->loadFromJsonObject($quanumOrder)->toArray();
            }
        }

        return $app['twig']->render('biobank/orders-quanum-today.html.twig', [
            'orders' => $orders
        ]);
    }

    public function ordersUnfinalizedAction(Application $app)
    {
        $unfinalizedOrders = $app['em']->getRepository('orders')->getUnfinalizedOrders();
        return $app['twig']->render('biobank/orders-unfinalized.html.twig', [
            'orders' => $unfinalizedOrders
        ]);
    }

    public function ordersUnlockedAction(Application $app)
    {
        $unlockedOrders = $app['em']->getRepository('orders')->getUnlockedOrders();
        return $app['twig']->render('biobank/orders-unlocked.html.twig', [
            'orders' => $unlockedOrders
        ]);
    }

    public function ordersRecentModifyAction(Application $app)
    {
        $recentModifyOrders = $app['em']->getRepository('orders')->getRecentModifiedOrders();
        return $app['twig']->render('biobank/orders-recent-modify.html.twig', [
            'orders' => $recentModifyOrders
        ]);
    }

}
