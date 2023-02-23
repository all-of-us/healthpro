<?php

namespace App\Controller;

use App\Entity\NphOrder;
use App\Form\ReviewTodayFilterType;
use App\Service\Nph\NphParticipantSummaryService;
use App\Service\ParticipantSummaryService;
use App\Service\ReviewService;
use App\Service\SiteService;
use DateTimeZone;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NphReviewController extends BaseController
{
    public const DATE_RANGE_LIMIT = 30;

    protected $participantSummaryService;

    protected $reviewService;

    protected $siteService;

    public function __construct(
        NphParticipantSummaryService $participantSummaryService,
        ReviewService $reviewService,
        SiteService $siteService,
        EntityManagerInterface $em
    ) {
        parent::__construct($em);
        $this->participantSummaryService = $participantSummaryService;
        $this->reviewService = $reviewService;
        $this->siteService = $siteService;
    }

    /**
     * @Route("/nph/review", name="nph_review_today")
     */
    public function index(Request $request): Response
    {
        $site = $this->siteService->getSiteId();
        if (!$site) {
            $this->addFlash('error', 'You must select a valid site');
            return $this->redirectToRoute('home');
        }

        $startDate = new DateTime('today', new DateTimeZone($this->getSecurityUser()->getTimeZone()));

        $endDate = new DateTime('tomorrow', new DateTimeZone($this->getSecurityUser()->getTimeZone()));

        $displayMessage = "Today's participants";
        $todayFilterForm = $this->createForm(ReviewTodayFilterType::class, null, ['timezone' => $this->getSecurityUser()->getTimeZone()]);
        $todayFilterForm->handleRequest($request);
        if ($todayFilterForm->isSubmitted()) {
            if ($todayFilterForm->isValid()) {
                $startDate = $todayFilterForm->get('start_date')->getData();
                $displayMessage = "Displaying results for {$startDate->format('m/d/Y')}";
                if ($todayFilterForm->get('end_date')->getData()) {
                    $endDate = $todayFilterForm->get('end_date')->getData();
                    $endDate->setTime(23, 59, 59);
                    // Check date range
                    if ($startDate->diff($endDate)->days >= self::DATE_RANGE_LIMIT) {
                        $todayFilterForm['start_date']->addError(new FormError('Date range cannot be more than 30 days'));
                    }
                    $displayMessage = "Displaying results from {$startDate->format('m/d/Y')} through {$endDate->format('m/d/Y')}";
                } else {
                    $endDate = new DateTime('tomorrow', new DateTimeZone($this->getSecurityUser()->getTimeZone()));
                }
            }
            if (!$todayFilterForm->isValid()) {
                $todayFilterForm->addError(new FormError('Please correct the errors below'));
                return $this->render('program/nph/review/today.html.twig', [
                    'participants' => [],
                    'todayFilterForm' => $todayFilterForm->createView(),
                    'displayMessage' => ''
                ]);
            }
        }


        $samples = $this->em->getRepository(NphOrder::class)->getOrdersByDateRange($site, $startDate, $endDate);
        $sampleCounts = $this->em->getRepository(NphOrder::class)->getSampleCollectionStatsByDate($site, $startDate, $endDate);

        $count = 0;
        $rowCounts = [];
        foreach (array_keys($samples) as $key) {
            if (!array_key_exists($samples[$key]['participantId'], $rowCounts)) {
                $rowCounts[$samples[$key]['participantId']]['participantRow'] = 0;
            }
            if (!array_key_exists('module'.$samples[$key]['module'], $rowCounts[$samples[$key]['participantId']])) {
                $rowCounts[$samples[$key]['participantId']]['module'.$samples[$key]['module']] = 0;
            }
            $rowCounts[$samples[$key]['participantId']]['participantRow'] += $samples[$key]['createdCount'] + 1;
            $rowCounts[$samples[$key]['participantId']]['module'.$samples[$key]['module']] += $samples[$key]['createdCount'] + 1;
            if ($count <= 5) {
                $samples[$key]['participant'] = $this->participantSummaryService->getParticipantById($samples[$key]['participantId']);
            }
            $samples[$key]['email'] = explode(",", $samples[$key]['email']);
            $samples[$key]['sampleId'] = explode(",", $samples[$key]['sampleId']);
            $samples[$key]['sampleCode'] = explode(",", $samples[$key]['sampleCode']);
            $samples[$key]['createdTs'] = explode(",", $samples[$key]['createdTs']);
            $samples[$key]['collectedTs'] = explode(",", $samples[$key]['collectedTs']);
            $samples[$key]['finalizedTs'] = explode(",", $samples[$key]['finalizedTs']);
            $count++;
        }

        return $this->render('/program/nph/review/today.html.twig', [
            'controller_name' => 'NphReviewController',
            'todayFilterForm' => $todayFilterForm->createView(),
            'displayMessage' => $displayMessage,
            'samples' => $samples,
            'timezone' => $this->getSecurityUser()->getTimeZone(),
            'collectedCount' => $sampleCounts[0]['collectedCount'],
            'finalizedCount' => $sampleCounts[0]['finalizedCount'],
            'createdCount' => $sampleCounts[0]['createdCount'],
            'rowCounts' => $rowCounts
        ]);
    }

    /**
     * @Route("/nph/participantname/lookup", name="nph_review_participant_lookup")
     */
    public function getParticipantName(Request $request)
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
            'lastName' => $participant->lastName,
            'biobankid' => $participant->biobankId
        ]);
    }

    /**
     * @Route("/nph/review/unfinalized", name="nph_review_unfinalized")
     */
    public function unfinalizedOrders()
    {
        $site = $this->siteService->getSiteId();
        if (!$site) {
            $this->addFlash('error', 'You must select a valid site');
            return $this->redirectToRoute('home');
        }
        $samples = $this->em->getRepository(NphOrder::class)->getUnfinalizedSamples($site);
        $sampleCounts = $this->em->getRepository(NphOrder::class)->getUnfinalizedSampleCollectionStats($site);
        $count = 0;
        foreach (array_keys($samples) as $key) {
            if ($count <= 5) {
                $samples[$key]['participant'] = $this->participantSummaryService->getParticipantById($samples[$key]['participantId']);
            }
            $count++;
        }
        return $this->render('/program/nph/review/unfinalized.html.twig', [
            'samples' => $samples,
            'timezone' => $this->getSecurityUser()->getTimeZone(),
            'collectedCount' => $sampleCounts[0]['collectedCount'],
            'finalizedCount' => $sampleCounts[0]['finalizedCount'],
            'createdCount' => $sampleCounts[0]['createdCount'],
        ]);
    }

    /**
     * @Route("/nph/review/recentlymodified", name="nph_review_recently_modified")
     */
    public function recentlyModifiedOrders()
    {
        $site = $this->siteService->getSiteId();
        if (!$site) {
            $this->addFlash('error', 'You must select a valid site');
            return $this->redirectToRoute('home');
        }
        $samples = $this->em->getRepository(NphOrder::class)->getRecentlyModifiedSamples($site, new DateTime('-7 day'));
        $count = 0;
        foreach (array_keys($samples) as $key) {
            if ($count <= 5) {
                $samples[$key]['participant'] = $this->participantSummaryService->getParticipantById($samples[$key]['participantId']);
            }
            $count++;
        }
        return $this->render('/program/nph/review/recently_modified.html.twig', [
            'samples' => $samples,
            'timezone' => $this->getSecurityUser()->getTimeZone(),
        ]);
    }
}
