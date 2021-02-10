<?php

namespace App\Controller;

use App\Service\LoggerService;
use App\Service\SiteService;
use App\Service\WorkQueueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Helper\WorkQueue;
use Pmi\Audit\Log;

/**
 * @Route("/s/workqueue")
 */
class WorkQueueController extends AbstractController
{
    /**
     * @var SessionInterface
     */
    protected $session;
    /**
     * @var WorkQueueService
     */
    protected $workQueueService;
    /**
     * @var SiteService
     */
    protected $siteService;

    /**
     * WorkQueueController constructor.
     * @param SessionInterface $session
     * @param WorkQueueService $workQueueService
     * @param SiteService $siteService
     */
    public function __construct(SessionInterface $session, WorkQueueService $workQueueService, SiteService $siteService)
    {
        $this->session = $session;
        $this->workQueueService = $workQueueService;
        $this->siteService = $siteService;
    }

    /**
     * @Route("/", name="workqueue_index")
     */
    public function index(Request $request)
    {
        if ($this->isGranted('ROLE_USER')) {
            $awardee = $this->siteService->getSiteAwardee();
        }
        if ($this->isGranted('ROLE_AWARDEE')) {
            $awardees = $this->siteService->getSuperUserAwardees();
            if (!empty($awardees)) {
                if (($sessionAwardee = $this->session->get('awardeeOrganization')) && in_array($sessionAwardee, $awardees)) {
                    $awardee = $sessionAwardee;
                } else {
                    // Default to first organization
                    $awardee = $awardees[0];
                }
            }
        }
        if (empty($awardee)) {
            return $this->render('workqueue/no-organization.html.twig');
        }

        $params = array_filter($request->query->all());
        $filters = WorkQueue::$filters;

        if ($this->isGranted('ROLE_AWARDEE')) {
            // Add awardees list to filters
            $awardeesList = [];
            $awardeesList['awardee']['label'] = 'Awardee';
            foreach ($awardees as $awardee) {
                $awardeesList['awardee']['options'][$this->siteService->getAwardeeDisplayName($awardee)] = $awardee;
            }
            $awardeesList['awardee']['options']['Salivary Pilot'] = 'salivary_pilot';
            $filters = array_merge($filters, $awardeesList);

            // Set to selected awardee
            if (isset($params['awardee'])) {
                // Check if the super user has access to this awardee
                if ($params['awardee'] !== 'salivary_pilot' && !in_array($params['awardee'], $this->siteService->getSuperUserAwardees())) {
                    throw $this->createAccessDeniedException();
                }
                $awardee = $params['awardee'];
                unset($params['awardee']);
            }
            // Save selected (or default) awardee in session
            $this->session->set('workQueueAwardee', $awardee);

            // Remove patient status filter for awardee
            unset($filters['patientStatus']);
        }

        // Display current organization in the default patient status filter drop down label
        if (isset($filters['patientStatus'])) {
            $filters['patientStatus']['label'] = 'Patient Status at ' . $this->siteService->getOrganizationDisplayName($this->siteService->getSiteOrganization());
        }

        $sites = $this->siteService->getAwardeeSites($awardee);
        if (!empty($sites)) {
            //Add sites filter
            $sitesList = [];
            $sitesList['site']['label'] = 'Paired Site';
            foreach ($sites as $site) {
                if (!empty($site->getGoogleGroup())) {
                    $sitesList['site']['options'][$site->getName()] = $site->getGoogleGroup();
                }
            }
            $sitesList['site']['options']['Unpaired'] = 'UNSET';
            $filters = array_merge($filters, $sitesList);

            //Add organization filter
            $organizationsList = [];
            $organizationsList['organization_id']['label'] = 'Paired Organization';
            foreach ($sites as $site) {
                if (!empty($site->getOrganizationId())) {
                    $organizationsList['organization_id']['options'][$this->siteService->getOrganizationDisplayName($site->getOrganizationId())] = $site->getOrganizationId();
                }
            }
            $organizationsList['organization_id']['options']['Unpaired'] = 'UNSET';
            $filters = array_merge($filters, $organizationsList);
        }

        //For ajax requests
        if ($request->isXmlHttpRequest()) {
            $params = array_merge($params, array_filter($request->request->all()));
            if (!empty($params['patientStatus'])) {
                $params['siteOrganizationId'] = $this->siteService->getSiteOrganization();
            }
            $participants = $this->workQueueService->participantSummarySearch($awardee, $params, $type = 'wQTable');
            $ajaxData = [];
            $ajaxData['recordsTotal'] = $ajaxData['recordsFiltered'] = $this->workQueueService->getTotal();
            $ajaxData['data'] = $this->workQueueService->generateTableRows($participants);
            $responseCode = 200;
            if ($this->workQueueService->getRdrError()) {
                $responseCode = 500;
            }
            return $this->json($ajaxData, $responseCode);
        } else {
            return $this->render('workqueue/index.html.twig', [
                'filters' => $filters,
                'surveys' => WorkQueue::$surveys,
                'samples' => WorkQueue::$samples,
                'participants' => [],
                'params' => $params,
                'awardee' => $awardee,
                'isRdrError' => $this->workQueueService->getRdrError(),
                'samplesAlias' => WorkQueue::$samplesAlias,
                'canExport' => $this->workQueueService->canExport(),
                'exportConfiguration' => $this->workQueueService->getExportConfiguration()
            ]);
        }
    }

    /**
     * @Route("/export", name="workqueue_export")
     */
    public function exportAction(Request $request, LoggerService $loggerService)
    {
        if (!$this->workQueueService->canExport()) {
            throw $this->createAccessDeniedException();
        }
        if ($this->isGranted('ROLE_AWARDEE')) {
            $awardee = $this->session->get('workQueueAwardee');
            $site = $this->siteService->getAwardeeId();
        } else {
            $awardee = $this->siteService->getSiteAwardee();
            $site = $this->siteService->getSiteId();
        }
        if (!$awardee) {
            return $this->render('workqueue/no-organization.html.twig');
        }

        $exportConfiguration = $this->workQueueService->getExportConfiguration();
        $limit = $exportConfiguration['limit'];
        $pageSize = $exportConfiguration['pageSize'];

        $params = array_filter($request->query->all());
        $params['_count'] = $pageSize;
        $params['_sort:desc'] = 'consentForStudyEnrollmentAuthored';
        if (!empty($params['patientStatus'])) {
            $params['siteOrganizationId'] = $this->siteService->getSiteOrganization();
        }

        $stream = function () use ($params, $awardee, $limit, $pageSize) {
            $output = fopen('php://output', 'w');
            // Add UTF-8 BOM
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output,
                ['This file contains information that is sensitive and confidential. Do not distribute either the file or its contents.']);
            fwrite($output, "\"\"\n");

            fputcsv($output, WorkQueue::getExportHeaders());

            for ($i = 0; $i < ceil($limit / $pageSize); $i++) {
                $participants = $this->workQueueService->participantSummarySearch($awardee, $params);
                foreach ($participants as $participant) {
                    fputcsv($output, $this->workQueueService->generateExportRow($participant));
                }
                unset($participants);
                if (!$this->workQueueService->getNextToken()) {
                    break;
                }
            }
            fwrite($output, "\"\"\n");
            fputcsv($output, ['Confidential Information']);
            fclose($output);
        };
        $filename = "workqueue_{$awardee}_" . date('Ymd-His') . '.csv';

        $loggerService->log(Log::WORKQUEUE_EXPORT, [
            'filter' => $params,
            'site' => $site
        ]);

        return new StreamedResponse($stream, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }
}
