<?php
namespace Pmi\Controller;

use Silex\Application;
use Pmi\Order\Order;

class OrderController extends AbstractController
{
    protected static $routes = [
        ['biobankSummary', '/participant/{participantId}/order/{orderId}/biobank/summary']
    ];

    protected function loadOrder($participantId, $orderId, Application $app)
    {
        $order = new Order($app);
        $order->loadOrder($participantId, $orderId);
        if (!$order->isValid()) {
            $app->abort(404);
        }
        if (!$order->canEdit() || $app->isTestSite()) {
            $app->abort(403);
        }

        return $order;
    }

    public function biobankSummaryAction($participantId, $orderId, Application $app)
    {
        $order = $this->loadOrder($participantId, $orderId, $app);
        return $app['twig']->render('biobank/summary.html.twig', [
            'biobankChanges' => $order->getBiobankChangesDetails()
        ]);
    }
}
