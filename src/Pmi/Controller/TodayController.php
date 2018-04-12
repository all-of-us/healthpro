<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class TodayController extends AbstractController
{
    protected static $name = 'today';

    protected static $routes = [
        ['home', '/']
    ];

    public function homeAction(Application $app, Request $request)
    {
        $site = $app->getSiteId();
        if (!$site) {
            $app->addFlashError('You must select a valid site');
            return $app->redirectToRoute('home');
        }

        // Get beginning of today (at midnight) in user's timezone
        $startTime = new \DateTime('today', new \DateTimeZone($app->getUserTimezone()));
        // Get MySQL date/time string in UTC
        $startTime->setTimezone(new \DateTimezone('UTC'));
        $today = $startTime->format('Y-m-d H:i:s');

        $participants = [];
        $emptyParticipant = [
            'order' => null,
            'orderCount' => 0,
            'finalizedSamples' => null,
            'physicalMeasurement' => null
        ];

        $ordersQuery = 'select id, participant_id, order_id, created_ts, collected_ts, processed_ts, finalized_ts, finalized_samples, ' .
            'greatest(coalesce(created_ts, 0), coalesce(collected_ts, 0), coalesce(processed_ts, 0), coalesce(finalized_ts, 0)) as latest_ts ' .
            'from orders where ' .
            '(created_ts >= :today OR collected_ts >= :today OR processed_ts >= :today OR finalized_ts >= :today) ' .
            'AND (site = :site OR collected_site = :site OR processed_site = :site OR finalized_site = :site) ' .
            'order by latest_ts desc';
        $orders = $app['db']->fetchAll($ordersQuery, [
            'today' => $today,
            'site' => $site
        ]);
        foreach ($orders as $order) {
            if (!array_key_exists($order['participant_id'], $participants)) {
                if ($order['finalized_samples'] && ($samples = json_decode($order['finalized_samples'])) && is_array($samples)) {
                    $finalizedSamples = count($samples);
                } else {
                    $finalizedSamples = null;
                }
                $participant = $emptyParticipant;
                $participant['order'] = $order;
                $participant['orderCount'] = 1;
                $participant['finalizedSamples'] = $finalizedSamples;
                $participants[$order['participant_id']] = $participant;
            } else {
                $participants[$order['participant_id']]['orderCount']++;
            }
        }

        $measurementsQuery = 'select id, participant_id, created_ts, finalized_ts, coalesce(finalized_ts, created_ts) as latest_ts ' .
            'from evaluations where ' .
            '(created_ts >= :today OR finalized_ts >= :today) ' .
            'AND (site = :site OR finalized_site = :site) ' .
            'order by latest_ts desc';
        $measurements = $app['db']->fetchAll($measurementsQuery, [
            'today' => $today,
            'site' => $site
        ]);
        foreach ($measurements as $measurement) {
            if (!array_key_exists($measurement['participant_id'], $participants)) {
                $participant = $emptyParticipant;
                $participant['physicalMeasurement'] = $measurement;
                $participants[$measurement['participant_id']] = $participant;
            } elseif (is_null($participants[$measurement['participant_id']]['physicalMeasurement'])) {
                $participants[$measurement['participant_id']]['physicalMeasurement'] = $measurement;
            }
        }

        return $app['twig']->render('today/index.html.twig', [
            'participants' => $participants
        ]);
    }
}
