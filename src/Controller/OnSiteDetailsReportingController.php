<?php

namespace App\Controller;

use App\Repository\IncentiveRepository;
use App\Repository\PatientStatusRepository;
use App\Service\OnSiteDetailsReportingService;
use App\Service\SiteService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/on-site")
 */
class OnSiteDetailsReportingController extends BaseController
{
    /**
     * @Route("/patient-status", name="on_site_patient_status")
     */
    public function patientStatusAction(OnSiteDetailsReportingService $onSiteDetailsReportingService, PatientStatusRepository $patientStatusRepository, SiteService $siteService, Request $request)
    {
        $params = $request->query->all();
        if ($request->isXmlHttpRequest()) {
            $ajaxParams = $request->request->all();
            $ajaxParams['startDate'] = !empty($params['startDate']) ? \DateTime::createFromFormat('!m/d/Y', $params['startDate']) : '';
            $ajaxParams['endDate'] = !empty($params['endDate']) ? \DateTime::createFromFormat('!m/d/Y', $params['endDate']) : '';
            $ajaxParams['participantId'] = $params['participantId'] ?? '';
            $sortColumns = $onSiteDetailsReportingService::$patientStatusSortColumns;
            $ajaxParams['sortColumn'] = $sortColumns[$ajaxParams['order'][0]['column']];
            $ajaxParams['sortDir'] = $ajaxParams['order'][0]['dir'];
            $patientStatuses = $patientStatusRepository->getOnsitePatientStatuses($siteService->getSiteAwardee(), $ajaxParams);
            $ajaxData = [];
            $ajaxData['data'] = $onSiteDetailsReportingService->getAjaxData($patientStatuses);
            $ajaxData['recordsTotal'] = $ajaxData['recordsFiltered'] = $patientStatusRepository->getOnsitePatientStatusesCount($siteService->getSiteAwardee(), $params);
            return $this->json($ajaxData);
        } else {
            return $this->render('onsite/patient-status.html.twig', ['params' => $params]);
        }
    }

    /**
     * @Route("/patient-status-export", name="on_site_patient_status_export")
     */
    public function patientStatusExportAction(OnSiteDetailsReportingService $onSiteDetailsReportingService, PatientStatusRepository $patientStatusRepository, SiteService $siteService, Request $request)
    {
        $queryParams = $request->query->all();
        $params = [];
        $params['startDate'] = !empty($queryParams['startDate']) ? \DateTime::createFromFormat('m/d/Y', $queryParams['startDate']) : '';
        $params['endDate'] = !empty($queryParams['endDate']) ? \DateTime::createFromFormat('m/d/Y', $queryParams['endDate']) : '';
        $patientStatuses = $patientStatusRepository->getOnsitePatientStatuses($siteService->getSiteAwardee(), $params);
        $records = $onSiteDetailsReportingService->getAjaxData($patientStatuses);
        $exportHeaders = $onSiteDetailsReportingService::$patientStatusExportHeaders;
        $stream = function () use ($records, $exportHeaders) {
            $output = fopen('php://output', 'w');
            // Add UTF-8 BOM
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv(
                $output,
                ['This file contains information that is sensitive and confidential. Do not distribute either the file or its contents.']
            );
            fwrite($output, "\"\"\n");

            fputcsv($output, $exportHeaders);

            foreach ($records as $record) {
                fputcsv($output, $record);
            }
            fwrite($output, "\"\"\n");
            fputcsv($output, ['Confidential Information']);
            fclose($output);
        };
        $filename = "on_site_details_patient_status_" . date('Ymd-His') . '.csv';

        return new StreamedResponse($stream, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    /**
     * @Route("/incentive-tracking", name="on_site_incentive_tracking")
     */
    public function incentiveTrackingAction(OnSiteDetailsReportingService $onSiteDetailsReportingService, IncentiveRepository $incentiveRepository, SiteService $siteService, Request $request)
    {
        $params = $request->query->all();
        if ($request->isXmlHttpRequest()) {
            $ajaxParams = $request->request->all();
            $ajaxParams['startDate'] = !empty($params['startDate']) ? \DateTime::createFromFormat('!m/d/Y', $params['startDate']) : '';
            $ajaxParams['endDate'] = !empty($params['endDate']) ? \DateTime::createFromFormat('!m/d/Y', $params['endDate']) : '';
            $ajaxParams['startDateOfService'] = !empty($params['startDateOfService']) ? \DateTime::createFromFormat('!m/d/Y', $params['startDateOfService']) : '';
            $ajaxParams['endDateOfService'] = !empty($params['endDateOfService']) ? \DateTime::createFromFormat('!m/d/Y', $params['endDateOfService']) : '';
            $ajaxParams['participantId'] = $params['participantId'] ?? '';
            $sortColumns = $onSiteDetailsReportingService::$incentiveSortColumns;
            $ajaxParams['sortColumn'] = $sortColumns[$ajaxParams['order'][0]['column']];
            $ajaxParams['sortDir'] = $ajaxParams['order'][0]['dir'];
            $incentives = $incentiveRepository->getOnsiteIncentives($siteService->getSiteId(), $ajaxParams);
            $ajaxData = [];
            $ajaxData['data'] = $onSiteDetailsReportingService->getIncentiveTrackingAjaxData($incentives);
            $ajaxData['recordsTotal'] = $ajaxData['recordsFiltered'] = $incentiveRepository->getOnsiteIncentivesCount($siteService->getSiteId(), $params);
            return $this->json($ajaxData);
        } else {
            return $this->render('onsite/incentive-tracking.html.twig', ['params' => $params]);
        }
    }

    /**
     * @Route("/incentive-tracking-export", name="on_site_incentive_tracking_export")
     */
    public function incentiveTrackingExportAction(OnSiteDetailsReportingService $onSiteDetailsReportingService, IncentiveRepository $incentiveRepository, SiteService $siteService, Request $request)
    {
        //TODO
    }
}
