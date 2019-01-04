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
        ['participants', '/participants', ['method' => 'GET|POST']],
        ['orders', '/orders', ['method' => 'GET|POST']],
        ['participant', '/participant/{id}', ['method' => 'GET|POST']],
        ['order', '/participant/{participantId}/order/{orderId}'],
        ['todayParticipants', '/review/today/participants'],
        ['unfinalizedOrders', '/review/unfinalized/orders'],
        ['unfinalizedMeasurements', '/review/unfinalized/measurements'],
        ['measurementsRecentModify', '/review/measurements/recent/modify'],
        ['ordersRecentModify', '/review/orders/recent/modify']
    ];

    public function participantsAction(Application $app, Request $request)
    {
        $idForm = $app['form.factory']->createNamedBuilder('id', Type\FormType::class)
            ->add('biobankId', Type\TextType::class, [
                'label' => 'Biobank ID',
                'constraints' => [
                    new Constraints\NotBlank(),
                    new Constraints\Type('string')
                ],
                'attr' => [
                    'placeholder' => 'Y000000000'
                ]
            ])
            ->getForm();

        $idForm->handleRequest($request);

        if ($idForm->isValid()) {
            $searchParameters = $idForm->getData();
            try {
                $searchResults = $app['pmi.drc.participants']->search($searchParameters);
                if (count($searchResults) == 1) {
                    return $app->redirectToRoute('biobank_participant', [
                        'id' => $searchResults[0]->id
                    ]);
                }
                return $app['twig']->render('participants-list.html.twig', [
                    'participants' => $searchResults
                ]);
            } catch (ParticipantSearchExceptionInterface $e) {
                $idForm->addError(new FormError($e->getMessage()));
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

            $order = $app['em']->getRepository('orders')->fetchOneBy([
                'order_id' => $id
            ]);
            if ($order) {
                return $app->redirectToRoute('order', [
                    'participantId' => $order['participant_id'],
                    'orderId' => $order['id']
                ]);
            }
            $app->addFlashError('Order ID not found');
        }

        return $app['twig']->render('biobank/orders.html.twig', [
            'idForm' => $idForm->createView()
        ]);
    }


    public function participantAction($id, Application $app, Request $request)
    {
        $refresh = $request->query->get('refresh');
        $participant = $app['pmi.drc.participants']->getById($id, $refresh);
        if ($refresh) {
            return $app->redirectToRoute('biobank_participant', [
                'id' => $id
            ]);
        }
        if (!$participant) {
            $app->abort(404);
        }

        $order = new Order($app);
        $orders = $order->getParticipantOrdersWithHistory($id);

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

        return $app['twig']->render('biobank/participant.html.twig', [
            'participant' => $participant,
            'orders' => $orders,
            'cacheEnabled' => $app['pmi.drc.participants']->getCacheEnabled()
        ]);
    }

    public function orderAction($participantId, $orderId, Application $app, Request $request)
    {
        $participant = $app['pmi.drc.participants']->getById($participantId);
        if (!$participant) {
            $app->abort(404);
        }
        $order = new Order($app);
        $order->loadOrder($participantId, $orderId);
        if (!$order->isValid()) {
            $app->abort(404);
        }
        if (!$order->getParticipant()->status || $app->isTestSite()) {
            $app->abort(403);
        }
        return $app['twig']->render('biobank/order.html.twig', [
            'participant' => $participant,
            'order' => $order->toArray(),
            'samplesInfo' => $order->getSamplesInfo(),
        ]);
    }

    public function todayParticipantsAction(Application $app, Request $request)
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

        $review = new Review;
        // TODO remove measurements and site
        $site = $app->getSiteId();
        $participants = $review->getTodayParticipants($app['db'], $today, $site);

        // Preload first 5 names
        $count = 0;
        foreach (array_keys($participants) as $id) {
            $participants[$id]['participant'] = $app['pmi.drc.participants']->getById($id);
            if (++$count >= 5) {
                break;
            }
        }

        return $app['twig']->render('biobank/today-participants.html.twig', [
            'participants' => $participants
        ]);
    }

    public function unfinalizedOrdersAction(Application $app)
    {
        $order = new Order($app);
        $unlockedOrders = $order->getUnlockedOrders();
        $unfinalizedOrders = $order->getUnfinalizedOrders();
        $orders = array_merge($unlockedOrders, $unfinalizedOrders);
        //print_r($orders); exit;
        return $app['twig']->render('biobank/unfinalized-orders.html.twig', [
            'orders' => $orders
        ]);
    }

    public function ordersRecentModifyAction(Application $app)
    {
        $order = new Order($app);
        $recentModifyOrders = $order->getRecentModifiedOrders();
        return $app['twig']->render('biobank/orders-recent-modify.html.twig', [
            'orders' => $recentModifyOrders
        ]);
    }

}
