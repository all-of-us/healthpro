<?php
namespace Pmi\Controller;

use Pmi\Evaluation\Evaluation;
use Silex\Application;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Csrf\CsrfToken;
use Pmi\Order\Order;
use Pmi\Review\Review;

class ReviewController extends AbstractController
{
    const DATE_RANGE_LIMIT = 7;

    protected static $name = 'review';

    protected static $routes = [
        ['today', '/', ['method' => 'GET|POST']],
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

        $review = new Review($app['db']);

        // Get beginning of today (at midnight) in user's timezone
        $startDate = new \DateTime('today', new \DateTimeZone($app->getUserTimezone()));

        // Get end of today (at midnight) in user's timezone
        $endDate = new \DateTime('yesterday 1 sec ago', new \DateTimeZone($app->getUserTimezone()));

        $displayMessage = "Today's participants";
        $todayFilterForm = $review->getTodayFilterForm($app['form.factory'], $app->getUserTimezone());
        $todayFilterForm->handleRequest($request);
        if ($todayFilterForm->isSubmitted()) {
            if ($todayFilterForm->isValid()) {
                $startDate = new \DateTime($todayFilterForm->get('start_date')->getData()->format('Y-m-d'), new \DateTimeZone($app->getUserTimezone()));
                $displayMessage = "Displaying results for {$startDate->format('m/d/Y')} date";
                if ($todayFilterForm->get('end_date')->getData()) {
                    $endDate = new \DateTime($todayFilterForm->get('end_date')->getData()->format('Y-m-d'), new \DateTimeZone($app->getUserTimezone()));
                    $endDate->setTime(23, 59, 59);
                    // Check date range
                    if ($startDate->diff($endDate)->days > self::DATE_RANGE_LIMIT) {
                        $todayFilterForm['start_date']->addError(new FormError('Start date and End date range should not be greater than 7 days'));
                    }
                    $displayMessage = "Displaying results from  {$startDate->format('m/d/Y')} to {$endDate->format('m/d/Y')} dates";
                } else {
                    $endDate = clone $startDate;
                    $endDate->setTime(23, 59, 59);
                }
            }
            if (!$todayFilterForm->isValid()) {
                $todayFilterForm->addError(new FormError('Please correct the errors below'));
                return $app['twig']->render('review/today.html.twig', [
                    'participants' => [],
                    'todayFilterForm' => $todayFilterForm->createView(),
                    'displayMessage' => ''
                ]);
            }
        }

        // Get MySQL date/time string in UTC
        $startDate->setTimezone(new \DateTimezone('UTC'));
        $startDate = $startDate->format('Y-m-d H:i:s');

        $endDate->setTimezone(new \DateTimezone('UTC'));
        $endDate = $endDate->format('Y-m-d H:i:s');

        $participants = $review->getTodayParticipants($startDate, $endDate, $site);
        
        // Preload first 5 names
        $count = 0;
        foreach (array_keys($participants) as $id) {
            $participants[$id]['participant'] = $app['pmi.drc.participants']->getById($id);
            if (++$count >= 5) {
                break;
            }
        }

        return $app['twig']->render('review/today.html.twig', [
            'participants' => $participants,
            'todayFilterForm' => $todayFilterForm->createView(),
            'displayMessage' => $displayMessage
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
        $unlockedOrders = $app['em']->getRepository('orders')->getSiteUnlockedOrders($app->getSiteId());
        $unfinalizedOrders = $app['em']->getRepository('orders')->getSiteUnfinalizedOrders($app->getSiteId());
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
        $measurements = $app['em']->getRepository('evaluations')->getSiteUnfinalizedEvaluations($site);

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
        $recentModifyMeasurements = $app['em']->getRepository('evaluations')->getSiteRecentModifiedEvaluations($site);
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
        $recentModifyOrders = $app['em']->getRepository('orders')->getSiteRecentModifiedOrders($app->getSiteId());
        return $app['twig']->render('review/orders-recent-modify.html.twig', [
            'orders' => $recentModifyOrders
        ]);
    }
}
