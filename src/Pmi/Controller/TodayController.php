<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class TodayController extends AbstractController
{
    protected static $name = 'today';

    protected static $routes = [
        ['home', '/'],
        ['orders', '/orders'],
        ['measurements', '/measurements'],
        ['participantNameLookup', '/participant/lookup']
    ];
    protected static $orderStatus = [
        'created_ts' => 'Created',
        'collected_ts' => 'Collected',
        'processed_ts' => 'Processed',
        'finalized_ts' => 'Finalized'
    ];
    protected static $measurementsStatus = [
        'created_ts' => 'Created',
        'finalized_ts' => 'Finalized'
    ];

    public function homeAction(Application $app, Request $request)
    {
        $site = $app->getSiteId();
        if (!$site) {
            $app->addFlashError('You must select a valid site');
            return $app->redirectToRoute('home');
        }

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

        $participants = [];
        $emptyParticipant = [
            'order' => null,
            'orderCount' => 0,
            'orderStatus' => '',
            'finalizedSamples' => null,
            'physicalMeasurement' => null,
            'physicalMeasurementCount' => 0,
            'physicalMeasurementStatus' => ''
        ];

        $ordersQuery = 'SELECT participant_id, \'order\' as type, id, order_id, created_ts, collected_ts, processed_ts, finalized_ts, finalized_samples, ' .
            'greatest(coalesce(created_ts, 0), coalesce(collected_ts, 0), coalesce(processed_ts, 0), coalesce(finalized_ts, 0)) AS latest_ts ' .
            'FROM orders WHERE ' .
            '(created_ts >= :today OR collected_ts >= :today OR processed_ts >= :today OR finalized_ts >= :today) ' .
            'AND (site = :site OR collected_site = :site OR processed_site = :site OR finalized_site = :site) ';
        $measurementsQuery = 'SELECT participant_id, \'measurement\' as type, id, null, created_ts, null, null, finalized_ts, null, coalesce(finalized_ts, created_ts) as latest_ts ' .
            'FROM evaluations WHERE ' .
            '(created_ts >= :today OR finalized_ts >= :today) ' .
            'AND (site = :site OR finalized_site = :site)';
        $query = "($ordersQuery) UNION ($measurementsQuery) ORDER BY latest_ts DESC";
        $rows = $app['db']->fetchAll($query, [
            'today' => $today,
            'site' => $site
        ]);

        foreach ($rows as $row) {
            $participantId = $row['participant_id'];
            if (!array_key_exists($participantId, $participants)) {
                $participants[$participantId] = $emptyParticipant;
            }
            switch ($row['type']) {
                case 'order':
                    if (is_null($participants[$participantId]['order'])) {
                        $participants[$participantId]['order'] = $row;
                        $participants[$participantId]['orderCount'] = 1;
                        // Get order status
                        foreach (self::$orderStatus as $field => $status) {
                            if ($row[$field]) {
                                $participants[$participantId]['orderStatus'] = $status;
                            }
                        }
                        // Get number of finalized samples
                        if ($row['finalized_samples'] && ($samples = json_decode($row['finalized_samples'])) && is_array($samples)) {
                            $participants[$participantId]['finalizedSamples'] = count($samples);
                        }
                    } else {
                        $participants[$participantId]['orderCount']++;
                    }
                    break;
                case 'measurement':
                    if (is_null($participants[$participantId]['physicalMeasurement'])) {
                        $participants[$participantId]['physicalMeasurement'] = $row;
                        $participants[$participantId]['physicalMeasurementCount'] = 1;
                        // Get physical measurements status
                        foreach (self::$measurementsStatus as $field => $status) {
                            if ($row[$field]) {
                                $participants[$participantId]['physicalMeasurementStatus'] = $status;
                            }
                        }
                    } else {
                        $participants[$participantId]['physicalMeasurementCount']++;
                    }
                    break;
            }
        }

        // Preload first 5 names
        $count = 0;
        foreach ($participants as $id => $participant) {
            $participants[$id]['participant'] = $app['pmi.drc.participants']->getById($id);
            if (++$count >= 5) {
                break;
            }
        }

        return $app['twig']->render('today/index.html.twig', [
            'participants' => $participants
        ]);
    }

    public function participantNameLookupAction(Application $app, Request $request)
    {
        $id = trim($request->query->get('id'));
        if (!$id) {
            return new JsonResponse(false);
        }

        $participant = $app['pmi.drc.participants']->getById($id);
        if (!$participant) {
            return new JsonResponse(false);
        }

        return new JsonResponse([
            'id' => $id,
            'firstName' => $participant->firstName,
            'lastName' => $participant->lastName
        ]);
    }

    public function ordersAction(Application $app, Request $request)
    {
        $site = $app->getSiteId();
        if (!$site) {
            $app->addFlashError('You must select a valid site');
            return $app->redirectToRoute('home');
        }

        $orders = $app['em']->getRepository('orders')->fetchBySql(
            'site = ? AND finalized_ts IS NULL',
            [$app->getSiteId()],
            ['created_ts' => 'DESC']
        );

        return $app['twig']->render('today/orders.html.twig', [
            'orders' => $orders
        ]);
    }

    public function measurementsAction(Application $app, Request $request)
    {
        $site = $app->getSiteId();
        if (!$site) {
            $app->addFlashError('You must select a valid site');
            return $app->redirectToRoute('home');
        }

        $measurements = $app['em']->getRepository('evaluations')->fetchBySql(
            'site = ? AND finalized_ts IS NULL',
            [$app->getSiteId()],
            ['created_ts' => 'DESC']
        );

        return $app['twig']->render('today/measurements.html.twig', [
            'measurements' => $measurements
        ]);
    }
}
