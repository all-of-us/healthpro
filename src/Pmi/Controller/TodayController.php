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
                        if ($row['finalized_samples'] && ($samples = json_decode($row['finalized_samples'])) && is_array($samples)) {
                            $finalizedSamples = count($samples);
                        } else {
                            $finalizedSamples = null;
                        }
                        $participants[$participantId]['order'] = $row;
                        $participants[$participantId]['orderCount'] = 1;
                        $participants[$participantId]['finalizedSamples'] = $finalizedSamples;
                    } else {
                        $participants[$participantId]['orderCount']++;
                    }
                    break;
                case 'measurement':
                    if (is_null($participants[$participantId]['physicalMeasurement'])) {
                        $participants[$participantId]['physicalMeasurement'] = $row;
                    }
                    break;
            }
        }

        return $app['twig']->render('today/index.html.twig', [
            'participants' => $participants
        ]);
    }
}
