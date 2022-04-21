<?php

namespace App\Controller;

use App\Entity\DeceasedReport;
use App\Form\DeceasedReportReviewType;
use App\Form\DeceasedReportType;
use App\Service\DeceasedReportsService;
use App\Service\ParticipantSummaryService;
use App\Cache\DatastoreAdapter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/deceased-reports")
 */
class DeceasedReportsController extends BaseController
{
    public const ORG_PENDING_CACHE_TTL = 500;
    public const ORG_PENDING_CACHE_KEY = 'deceased_reports.org-%s.pending.count';

    protected $cache;

    public function __construct(
        ParameterBagInterface $params,
        EntityManagerInterface $em
    ) {
        parent::__construct($em);
        $this->cache = new DatastoreAdapter($params->get('ds_clean_up_limit'));
    }

    /**
     * @Route("/", name="deceased_reports_index")
     */
    public function participantObservationIndex(Request $request, SessionInterface $session, DeceasedReportsService $deceasedReportsService)
    {
        $statusFilter = $request->query->get('status', 'preliminary');
        $organizationId = $session->get('siteOrganizationId');
        if (!$organizationId) {
            throw $this->createAccessDeniedException('Must be associated with a valid Organization.');
        }
        $reports = $deceasedReportsService->getDeceasedReports($organizationId, $statusFilter);
        $reports = $this->formatReportTableRows($reports);
        return $this->render('deceasedreports/index.html.twig', [
            'statusFilter' => $statusFilter,
            'reports' => $reports
        ]);
    }

    /**
     * @Route("/{participantId}/{reportId}", name="deceased_report_review", requirements={"participantId"="P\d+","reportId"="\d+"})
     */
    public function deceasedReportReview(Request $request, ParticipantSummaryService $participantSummaryService, DeceasedReportsService $deceasedReportsService, SessionInterface $session, $participantId, $reportId)
    {
        $organizationId = $session->get('siteOrganizationId');
        $participant = $participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $fhirData = $deceasedReportsService->getDeceasedReportById($participantId, $reportId);
        $report = (new DeceasedReport())->loadFromFhirObservation($fhirData);
        if ($report->getSubmittedBy() === $this->getSecurityUser()->getEmail() && $report->getReportStatus() === 'preliminary') {
            $this->addFlash('notice', 'You submitted this report. Another user must review it.');
        }
        $form = $this->createForm(DeceasedReportReviewType::class, $report, ['reviewer_email' => $this->getSecurityUser()->getEmail()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $fhirData = $deceasedReportsService->buildDeceasedReportReviewFhir($data, $this->getSecurityUser());
                $deceasedReportsService->updateDeceasedReport($participantId, $reportId, $fhirData);
                $report->setReportStatus('preliminary');
                $this->resetPendingCountCache($organizationId);
                $this->addFlash('success', 'Report updated!');
                return $this->redirectToRoute('deceased_reports_index');
            } catch (\Exception $e) {
                error_log($e->getMessage());
                $report->setReportStatus('preliminary');
                $this->addFlash('error', sprintf('Invalid API response [%s]', $e->getCode()));
            }
        }
        return $this->render('deceasedreports/review.html.twig', [
            'report' => $report,
            'participant' => $participant,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/{participantId}/new", name="deceased_report_new", requirements={"participantId"="P\d+"})
     */
    public function deceasedReportNew(Request $request, ParticipantSummaryService $participantSummaryService, DeceasedReportsService $deceasedReportsService, SessionInterface $session, $participantId)
    {
        $organizationId = $session->get('siteOrganizationId');
        $participant = $participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        if ($participant->withdrawalStatus !== 'NOT_WITHDRAWN') {
            $this->addFlash('error', 'Cannot create Deceased Report on withdrawn participant.');
            return $this->redirectToRoute('participant', ['id' => $participantId]);
        }
        if ($participant->suspensionStatus !== 'NOT_SUSPENDED') {
            $this->addFlash('error', 'Cannot create Deceased Report on deactivated participant.');
            return $this->redirectToRoute('participant', ['id' => $participantId]);
        }
        $reports = $deceasedReportsService->getDeceasedReportsByParticipant($participantId);
        $report = new DeceasedReport();
        foreach ($reports as $record) {
            if ($record->status === 'cancelled') {
                continue;
            }
            $report = (new DeceasedReport())->loadFromFhirObservation($record);
        }
        $form = $this->createForm(DeceasedReportType::class, $report, ['disabled' => (bool) $report->getId()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $report = $form->getData();
            try {
                $fhirData = $deceasedReportsService->buildDeceasedReportFhir($report, $this->getSecurityUser());
                $response = $deceasedReportsService->createDeceasedReport($participantId, $fhirData);
                $report = $report->loadFromFhirObservation($response);
                $this->resetPendingCountCache($organizationId);
                $this->addFlash('success', 'Deceased Report created!');
                return $this->redirectToRoute('participant', ['id' => $participantId]);
            } catch (\Exception $e) {
                error_log($e->getMessage());
                $report->setReportStatus('preliminary');
                $this->addFlash('error', sprintf('Invalid API response [%s]', $e->getCode()));
            }
        }

        return $this->render('deceasedreports/new.html.twig', [
            'participant' => $participant,
            'report' => $report,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/{participantId}/history", name="deceased_report_history", requirements={"participantId"="P\d+"})
     */
    public function deceasedReporthHistory(Request $request, ParticipantSummaryService $participantSummaryService, DeceasedReportsService $deceasedReportsService, $participantId)
    {
        $participant = $participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $reports = $deceasedReportsService->getDeceasedReportsByParticipant($participantId);
        $reports = $this->formatReportTableRows($reports);

        return $this->render('deceasedreports/history.html.twig', [
            'participant' => $participant,
            'reports' => $reports
        ]);
    }

    /**
     * @Route("/stats", name="deceased_report_stats")
     */
    public function getStats(SessionInterface $session, DeceasedReportsService $deceasedReportsService)
    {
        $organizationId = $session->get('siteOrganizationId');
        $pendingReportCountCache = $this->cache->getItem(sprintf(self::ORG_PENDING_CACHE_KEY, $organizationId));
        if ($cacheHit = $pendingReportCountCache->isHit()) {
            $pendingReportCount = (int) $pendingReportCountCache->get();
        } else {
            $pendingReportCountCache->expiresAfter(self::ORG_PENDING_CACHE_TTL);
            $reports = $deceasedReportsService->getDeceasedReports($organizationId, 'preliminary');
            $pendingReportCount = count($reports);
            $pendingReportCountCache->set($pendingReportCount);
            $this->cache->save($pendingReportCountCache);
        }

        return $this->json([
            'pending' => $pendingReportCount,
            'cacheHit' => $cacheHit
        ]);
    }

    /* Private Methods */

    private function resetPendingCountCache($organizationId): bool
    {
        $this->cache->deleteItem(sprintf(self::ORG_PENDING_CACHE_KEY, $organizationId));
        return true;
    }

    private function formatReportTableRows($reports = []): array
    {
        $rows = [];
        if (!is_array($reports) || count($reports) === 0) {
            return [];
        }
        foreach ($reports as $report) {
            $rows[] = (new DeceasedReport())->loadFromFhirObservation($report);
        }
        return $rows;
    }
}
