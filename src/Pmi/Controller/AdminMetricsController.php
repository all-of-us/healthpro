<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Pmi\Service\MetricsService;
use DateTime;
use DateTimeImmutable;

class AdminMetricsController extends AbstractController
{
    protected static $name = 'admin_metrics';

    protected static $routes = [
        ['home', '/']
    ];

    public function homeAction(Application $app, Request $request)
    {
        switch ($request->query->get('range')) {
            case 'week':
                $end = new DateTime('now');
                $end->setTime($end->format('G'), 0); // beginning of current hour
                $end = DateTimeImmutable::createFromMutable($end);
                $start = $end->modify('-7 days');
                $resolution = 'hour';
                break;
            case 'year':
                $end = new DateTimeImmutable('midnight first day of this month');
                $start = $end->modify('-1 year');
                $resolution = 'month';
                break;
            case 'month':
            default:
                $end = new DateTimeImmutable('today');
                $start = $end->modify('-30 days');
                $resolution = 'day';
        }
        $metrics = new MetricsService($app['db']);
        $metrics->setRange($start, $end, $resolution);

        $ordersChartData = $metrics->getBiobankOrders();
        $ordersTotal = array_sum($ordersChartData);

        $pmsChartData = $metrics->getPhysicalMeasurements();
        $pmsTotal = array_sum($pmsChartData);

        return $app['twig']->render('admin/metrics/index.html.twig', [
            'resolution' => $resolution,
            'metrics' => [
                'orders' => [
                    'name' => 'Biobank Orders',
                    'total' => $ordersTotal,
                    'chart' => $ordersChartData
                ],
                'physical_measurements' => [
                    'name' => 'Physical Measurements',
                    'total' => $pmsTotal,
                    'chart' => $pmsChartData
                ]
            ]
        ]);
    }
}
