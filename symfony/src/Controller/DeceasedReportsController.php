<?php
namespace App\Controller;

use App\Entity\DeceasedReport;
use App\Form\DeceasedReportReviewType;
use App\Form\DeceasedReportType;
use App\Service\DeceasedReportsService;
use App\Service\LoggerService;
use App\Service\ParticipantSummaryService;
use Pmi\Audit\Log;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s/deceased-reports")
 */
class DeceasedReportsController extends AbstractController
{
    /**
     * @Route("/", name="deceased_reports_index")
     */
    public function participantObservationIndex(Request $request, SessionInterface $session, DeceasedReportsService $deceasedReportsService) {
        $statusFilter = $request->query->get('status', 'preliminary');
        $organizationId = ($request->query->get('all', false) === false) ? $session->get('siteOrganizationId', null) : null;
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
    public function deceasedReportReview(Request $request, ParticipantSummaryService $participantSummaryService, DeceasedReportsService $deceasedReportsService, $participantId, $reportId) {
        $participant = $participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $fhirData = $deceasedReportsService->getDeceasedReportById($participantId, $reportId);
        $report = (new DeceasedReport())->loadFromFhirObservation($fhirData);
        if ($report->getSubmittedBy() == $this->getUser()->getEmail() && $report->getReportStatus() == 'preliminary') {
            $this->addFlash('notice', 'You submitted this report. Another user must review it.');
        }
        $form = $this->createForm(DeceasedReportReviewType::class, $report, ['reviewer_email' => $this->getUser()->getEmail()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $fhirData = $deceasedReportsService->buildDeceasedReportReviewFhir($data, $this->getUser());
                $deceasedReportsService->updateDeceasedReport($participantId, $reportId, $fhirData);
                $this->addFlash('success', 'Report updated!');
                return $this->redirectToRoute('deceased_reports_index');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
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
    public function deceasedReportNew(Request $request, ParticipantSummaryService $participantSummaryService, DeceasedReportsService $deceasedReportsService, $participantId) {
        $participant = $participantSummaryService->getParticipantById($participantId);
        if (!$participant) {
            throw $this->createNotFoundException('Participant not found.');
        }
        $reports = $deceasedReportsService->getDeceasedReportsByParticipant($participantId);
        $report = new DeceasedReport();
        foreach ($reports as $record) {
            if ($record) {
                $report = (new DeceasedReport())->loadFromFhirObservation($record);
            }
        }
        $form = $this->createForm(DeceasedReportType::class, $report, ['disabled' => (bool) $report->getId()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $report = $form->getData();
            try {
                $fhirData = $deceasedReportsService->buildDeceasedReportFhir($report, $this->getUser());
                $response = $deceasedReportsService->createDeceasedReport($participantId, $fhirData);
                $report = $report->loadFromFhirObservation($response);
                $this->addFlash('success', 'Deceased Report created!');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('deceasedreports/new.html.twig', [
            'participant' => $participant,
            'report' => $report,
            'form' => $form->createView()
        ]);
    }

    /* Private Methods */

    public function formatReportTableRows($reports = [])
    {
        $rows = [];
        if (!is_array($reports) || count($reports) == 0) {
            return [];
        }
        foreach ($reports as $report) {
            $rows[] = (new DeceasedReport())->loadFromFhirObservation($report);
        }
        return $rows;
    }

    public function filterReports($reports, $filter = [])
    {
        $rows = [];
        foreach ($reports as $report) {
            if (!empty($filter) && !in_array($report->status, $filter)) {
                continue;
            }
            $rows[] = $report;
        }

        return $rows;
    }

}
