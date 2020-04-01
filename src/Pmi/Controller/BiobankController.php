<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Form\FormError;
use Pmi\Drc\Exception\ParticipantSearchExceptionInterface;
use Pmi\Order\Order;
use Pmi\Review\Review;

class BiobankController extends AbstractController
{
    protected static $name = 'biobank';

    protected static $routes = [
        ['home', '/'],
        ['participants', '/participants', ['method' => 'GET|POST']],
        ['orders', '/orders', ['method' => 'GET|POST']],
        ['participant', '/{biobankId}'],
        ['order', '/{biobankId}/order/{orderId}'],
        ['quanumOrder', '/{biobankId}/quanum-order/{orderId}'],
        ['ordersToday', '/review/orders/today'],
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

        if ($idForm->isValid()) {
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

        if ($idForm->isValid()) {
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
                $order = (new Order())->loadFromJsonObject($quanumOrders[0])->toArray();
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
        foreach ($orders as $key => $order) {
            // Display most recent processed sample time if exists
            $processedSamplesTs = json_decode($order['processed_samples_ts'], true);
            if (is_array($processedSamplesTs) && !empty($processedSamplesTs)) {
                $processedTs = new \DateTime();
                $processedTs->setTimestamp(max($processedSamplesTs));
                $processedTs->setTimezone(new \DateTimeZone($app->getUserTimezone()));
                $orders[$key]['processed_ts'] = $processedTs;
            }
        }
        // Quanum Orders
        $quanumOrders = $app['pmi.drc.participants']->getOrdersByParticipant($participant->id);
        foreach ($quanumOrders as $quanumOrder) {
            if (in_array($quanumOrder->origin, ['careevolution'])) {
                $orders[] = (new Order())->loadFromJsonObject($quanumOrder)->toArray();
            }
        }

        return $app['twig']->render('biobank/participant.html.twig', [
            'participant' => $participant,
            'orders' => $orders,
            'cacheEnabled' => $app['pmi.drc.participants']->getCacheEnabled(),
            'biobankView' => true
        ]);
    }

    public function orderAction($biobankId, $orderId, Application $app)
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
        return $app['twig']->render('biobank/order.html.twig', [
            'participant' => $participant,
            'order' => $order->toArray(),
            'samplesInfo' => $order->getSamplesInfo(),
            'currentStep' => $currentStep
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
