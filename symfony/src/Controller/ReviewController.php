<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ReviewTodayFilterType;
use App\Repository\OrderRepository;
use App\Repository\MeasurementRepository;
use App\Service\ParticipantSummaryService;
use App\Service\LoggerService;
use App\Service\ReviewService;
use App\Service\SiteService;
use App\Service\TimezoneService;
use Doctrine\ORM\EntityManagerInterface;
use Pmi\Audit\Log;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints;

/**
 * @Route("/s/review")
 */
class ReviewController extends AbstractController
{
    const DATE_RANGE_LIMIT = 7;

    protected $session;

    protected $participantSummaryService;

    protected $reviewService;

    protected $siteService;

    public function __construct(SessionInterface $session, ParticipantSummaryService $participantSummaryService, ReviewService $reviewService, SiteService $siteService)
    {
        $this->session = $session;
        $this->participantSummaryService = $participantSummaryService;
        $this->reviewService = $reviewService;
        $this->siteService = $siteService;
    }

    /**
     * @Route("/", name="review_today")
     */
    public function today(Request $request)
    {
        $site = $this->siteService->getSiteId();
        if (!$site) {
            $this->addFlash('error', 'You must select a valid site');
            return $this->redirectToRoute('home');
        }

        // Get beginning of today (at midnight) in user's timezone
        $startDate = new \DateTime('today', new \DateTimeZone($this->getUser()->getTimeZone()));

        // Get beginning of tomorrow (at midnight) in user's timezone (will be comparing < $endDate)
        $endDate = new \DateTime('tomorrow', new \DateTimeZone($this->getUser()->getTimeZone()));

        $displayMessage = "Today's participants";
        $todayFilterForm = $this->createForm(ReviewTodayFilterType::class, null, ['timezone' => $this->getUser()->getTimeZone()]);
        $todayFilterForm->handleRequest($request);
        if ($todayFilterForm->isSubmitted()) {
            if ($todayFilterForm->isValid()) {
                $startDate = $todayFilterForm->get('start_date')->getData();
                $displayMessage = "Displaying results for {$startDate->format('m/d/Y')}";
                if ($todayFilterForm->get('end_date')->getData()) {
                    $endDate = $todayFilterForm->get('end_date')->getData();
                    // Check date range
                    if ($startDate->diff($endDate)->days >= self::DATE_RANGE_LIMIT) {
                        $todayFilterForm['start_date']->addError(new FormError('Date range cannot be more than 7 days'));
                    }
                    $displayMessage = "Displaying results from {$startDate->format('m/d/Y')} through {$endDate->format('m/d/Y')}";
                } else {
                    $endDate = clone $startDate;
                }
            }
            if (!$todayFilterForm->isValid()) {
                $todayFilterForm->addError(new FormError('Please correct the errors below'));
                return $this->render('review/today.html.twig', [
                    'participants' => [],
                    'todayFilterForm' => $todayFilterForm->createView(),
                    'displayMessage' => ''
                ]);
            }
        }

        // Use midnight of the following day to get the entire day, inclusive
        $endDate->modify('+1 day');

        // Get MySQL date/time string in UTC
        $startDate->setTimezone(new \DateTimezone('UTC'));
        $startDate = $startDate->format('Y-m-d H:i:s');

        $endDate->setTimezone(new \DateTimezone('UTC'));
        $endDate = $endDate->format('Y-m-d H:i:s');

        $participants = $this->reviewService->getTodayParticipants($startDate, $endDate, $site);

        // Preload first 5 names
        $count = 0;
        foreach (array_keys($participants) as $id) {
            $participants[$id]['participant'] = $this->participantSummaryService->getParticipantById($id);
            if (++$count >= 5) {
                break;
            }
        }

        return $this->render('review/today.html.twig', [
            'participants' => $participants,
            'todayFilterForm' => $todayFilterForm->createView(),
            'displayMessage' => $displayMessage
        ]);
    }

    /**
     * @Route("/orders", name="participant_review_unfinalized_orders")
     */
    public function unfinalizedOrders(Request $request, OrderRepository $orderRepository)
    {
        $site = $this->siteService->getSiteId();
        if (!$site) {
            $this->addFlash('error', 'You must select a valid site');
            return $this->redirectToRoute('home');
        }
        $unlockedOrders = $orderRepository->getSiteUnlockedOrders($site);
        $unfinalizedOrders = $orderRepository->getSiteUnfinalizedOrders($site);
        $orders = array_merge($unlockedOrders, $unfinalizedOrders);
        return $this->render('review/unfinalized_orders.html.twig', [
            'orders' => $orders
        ]);

    }

    /**
     * @Route("/measurements", name="participant_review_unfinalized_measurements")
     */
    public function unfinalizedMeasurements(Request $request, MeasurementRepository $measurementRepository)
    {
        $site = $this->siteService->getSiteId();
        if (!$site) {
            $this->addFlash('error', 'You must select a valid site');
            return $this->redirectToRoute('home');
        }
        $measurements = $measurementRepository->getSiteUnfinalizedEvaluations($site);

        return $this->render('review/unfinalized_measurements.html.twig', [
            'measurements' => $measurements
        ]);
    }

    /**
     * @Route("/orders/recent/modify", name="participant_review_modified_orders")
     */
    public function modifiedOrders(Request $request, OrderRepository $orderRepository)
    {
        $site = $this->siteService->getSiteId();
        if (!$site) {
            $this->addFlash('error', 'You must select a valid site');
            return $this->redirectToRoute('home');
        }
        $recentModifyOrders = $orderRepository->getSiteRecentModifiedOrders($site);
        return $this->render('review/modified_orders.html.twig', [
            'orders' => $recentModifyOrders
        ]);
    }

    /**
     * @Route("/measurements/recent/modify", name="participant_review_modified_measurements")
     */
    public function modifiedMeasurements(Request $request, MeasurementRepository $measurementRepository)
    {
        $site = $this->siteService->getSiteId();
        if (!$site) {
            $this->addFlash('error', 'You must select a valid site');
            return $this->redirectToRoute('home');
        }
        $recentModifyMeasurements = $measurementRepository->getSiteRecentModifiedEvaluations($site);
        return $this->render('review/modified_measurements.html.twig', [
            'measurements' => $recentModifyMeasurements
        ]);
    }

    /**
     * @Route("/participant/lookup", name="participant_review_name_lookup")
     */
    public function nameLookup(Request $request)
    {
        $id = trim($request->query->get('id'));
        if (!$id) {
            return $this->json(null);
        }

        $participant = $this->participantSummaryService->getParticipantById($id);
        if (!$participant) {
            return $this->json(null);
        }

        return $this->json([
            'id' => $id,
            'firstName' => $participant->firstName,
            'lastName' => $participant->lastName
        ]);
    }
}
