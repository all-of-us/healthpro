<?php
namespace Pmi\Controller;

use Pmi\Evaluation\Evaluation;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Csrf\CsrfToken;
use Pmi\Order\Order;
use Pmi\Review\Review;

class ReviewController extends AbstractController
{
    protected static $name = 'review';

    protected static $routes = [
        ['today', '/'],
        ['orders', '/orders'],
        ['measurements', '/measurements'],
        ['participantNameLookup', '/participant/lookup'],
        ['measurementsRecentModify', '/measurements/recent/modify'],
        ['ordersRecentModify', '/orders/recent/modify']
    ];

    public function todayAction(Application $app, Request $request)
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

        $review = new Review($app['db']);
        $participants = $review->getTodayParticipants($today, $site);
        
        // Preload first 5 names
        $count = 0;
        foreach (array_keys($participants) as $id) {
            $participants[$id]['participant'] = $app['pmi.drc.participants']->getById($id);
            if (++$count >= 5) {
                break;
            }
        }

        return $app['twig']->render('review/today.html.twig', [
            'participants' => $participants
        ]);
    }

    public function participantNameLookupAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('review', $request->get('csrf_token')))) {
            return new JsonResponse(['error' => 'Invalid request'], 403);
        }

        $id = trim($request->query->get('id'));
        if (!$id) {
            return new JsonResponse(null);
        }

        $participant = $app['pmi.drc.participants']->getById($id);
        if (!$participant) {
            return new JsonResponse(null);
        }

        return new JsonResponse([
            'id' => $id,
            'firstName' => $participant->firstName,
            'lastName' => $participant->lastName
        ]);
    }

    public function ordersAction(Application $app)
    {
        $site = $app->getSiteId();
        if (!$site) {
            $app->addFlashError('You must select a valid site');
            return $app->redirectToRoute('home');
        }
        $unlockedOrders = $app['em']->getRepository('order_repository')->getSiteUnlockedOrders($app->getSiteId());
        $unfinalizedOrders = $app['em']->getRepository('order_repository')->getSiteUnfinalizedOrders($app->getSiteId());
        $orders = array_merge($unlockedOrders, $unfinalizedOrders);
        return $app['twig']->render('review/orders.html.twig', [
            'orders' => $orders
        ]);
    }

    public function measurementsAction(Application $app)
    {
        $site = $app->getSiteId();
        if (!$site) {
            $app->addFlashError('You must select a valid site');
            return $app->redirectToRoute('home');
        }
        $measurements = $app['em']->getRepository('evaluation_repository')->getSiteUnfinalizedEvaluations($site);

        return $app['twig']->render('review/measurements.html.twig', [
            'measurements' => $measurements
        ]);
    }

    public function measurementsRecentModifyAction(Application $app)
    {
        $site = $app->getSiteId();
        if (!$site) {
            $app->addFlashError('You must select a valid site');
            return $app->redirectToRoute('home');
        }
        $recentModifyMeasurements = $app['em']->getRepository('evaluation_repository')->getSiteRecentModifiedEvaluations($site);
        return $app['twig']->render('review/measurements-recent-modify.html.twig', [
            'measurements' => $recentModifyMeasurements
        ]);
    }

    public function ordersRecentModifyAction(Application $app)
    {
        $site = $app->getSiteId();
        if (!$site) {
            $app->addFlashError('You must select a valid site');
            return $app->redirectToRoute('home');
        }
        $recentModifyOrders = $app['em']->getRepository('order_repository')->getSiteRecentModifiedOrders($app->getSiteId());
        return $app['twig']->render('review/orders-recent-modify.html.twig', [
            'orders' => $recentModifyOrders
        ]);
    }
}
