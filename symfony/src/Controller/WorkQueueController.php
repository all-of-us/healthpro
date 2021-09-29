<?php

namespace App\Controller;

use App\Entity\Measurement;
use App\Entity\Order;
use App\Entity\Problem;
use App\Form\WorkQueueParticipantLookupIdType;
use App\Form\WorkQueueParticipantLookupSearchType;
use App\Service\LoggerService;
use App\Service\OrderService;
use App\Service\ParticipantSummaryService;
use App\Service\SiteService;
use App\Service\WorkQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
    protected $session;
    protected $workQueueService;
    protected $siteService;
    protected $displayParticipantConsentsTab;

    public function __construct(SessionInterface $session, WorkQueueService $workQueueService, SiteService $siteService, ParameterBagInterface $params)
    {
        $this->session = $session;
        $this->workQueueService = $workQueueService;
        $this->siteService = $siteService;
        $this->displayParticipantConsentsTab = $params->has('feature.participantconsentsworkqueue') ? $params->get('feature.participantconsentsworkqueue') : false;
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
            $participants = $this->workQueueService->participantSummarySearch($awardee, $params, 'wQTable', WorkQueue::$sortColumns);
            $ajaxData = [];
            $ajaxData['recordsTotal'] = $ajaxData['recordsFiltered'] = $this->workQueueService->getTotal();
            $ajaxData['data'] = $this->workQueueService->generateTableRows($participants);
            $responseCode = 200;
            if ($this->workQueueService->isRdrError()) {
                $responseCode = 500;
            }
            return $this->json($ajaxData, $responseCode);
        } else {
            return $this->render('workqueue/index.html.twig', [
                'filters' => $filters,
                'surveys' => WorkQueue::$surveys,
                'samples' => WorkQueue::$samples,
                'digitalHealthSharingTypes' => WorkQueue::$digitalHealthSharingTypes,
                'participants' => [],
                'params' => $params,
                'awardee' => $awardee,
                'isRdrError' => $this->workQueueService->isRdrError(),
                'samplesAlias' => WorkQueue::$samplesAlias,
                'canExport' => $this->workQueueService->canExport(),
                'exportConfiguration' => $this->workQueueService->getExportConfiguration(),
                'displayParticipantConsentsTab' => $this->displayParticipantConsentsTab
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
        if ($this->displayParticipantConsentsTab && isset($params['exportType']) && $params['exportType'] === 'consents') {
            $exportHeaders = WorkQueue::getConsentExportHeaders();
            $exportRowMethod = 'generateConsentExportRow';
            $fileName = 'workqueue_consents';
        } else {
            $exportHeaders = WorkQueue::getExportHeaders();
            $exportRowMethod = 'generateExportRow';
            $fileName = 'workqueue';
        }
        $stream = function () use ($params, $awardee, $limit, $pageSize, $exportHeaders, $exportRowMethod) {
            $output = fopen('php://output', 'w');
            // Add UTF-8 BOM
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv(
                $output,
                ['This file contains information that is sensitive and confidential. Do not distribute either the file or its contents.']
            );
            fwrite($output, "\"\"\n");

            fputcsv($output, $exportHeaders);

            for ($i = 0; $i < ceil($limit / $pageSize); $i++) {
                $participants = $this->workQueueService->participantSummarySearch($awardee, $params);
                foreach ($participants as $participant) {
                    fputcsv($output, $this->workQueueService->$exportRowMethod($participant));
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
        $filename = "{$fileName}_{$awardee}_" . date('Ymd-His') . '.csv';

        $loggerService->log(Log::WORKQUEUE_EXPORT, [
            'filter' => $params,
            'site' => $site
        ]);

        return new StreamedResponse($stream, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }


    /**
     * @Route("/participant/{id}", name="workqueue_participant")
     */
    public function participantAction(
        $id,
        Request $request,
        ParticipantSummaryService $participantSummaryService,
        EntityManagerInterface $em,
        OrderService $orderService,
        ParameterBagInterface $params
    ) {
        $refresh = $request->query->get('refresh');
        $participant = $participantSummaryService->getParticipantById($id, $refresh);
        if ($refresh) {
            return $this->redirectToRoute('workqueue_participant', [
                'id' => $id
            ]);
        }
        if (!$participant) {
            throw $this->createNotFoundException();
        }

        if (!$this->isGranted('ROLE_AWARDEE_SCRIPPS')) {
            throw $this->createAccessDeniedException();
        }

        // Deny access if participant awardee does not belong to the allowed awardees or not a salivary participant (awardee = UNSET and sampleStatus1SAL2 = RECEIVED)
        if (!(in_array(
            $participant->awardee,
            $this->siteService->getSuperUserAwardees()
        ) || (empty($participant->awardee) && $participant->sampleStatus1SAL2 === 'RECEIVED'))) {
            throw $this->createAccessDeniedException();
        }

        $measurements = $em->getRepository(Measurement::class)->findBy(['participantId' => $id]);

        // Internal Orders
        $orders = $em->getRepository(Order::class)->findBy(['participantId' => $id]);

        // Quanum Orders
        $order = new Order();
        $orderService->loadSamplesSchema($order);
        $quanumOrders = $orderService->getOrdersByParticipant($participant->id);
        foreach ($quanumOrders as $quanumOrder) {
            if (in_array($quanumOrder->origin, ['careevolution'])) {
                $orders[] = $orderService->loadFromJsonObject($quanumOrder);
            }
        }

        $problems = $em->getRepository(Problem::class)->getProblemsWithCommentsCount($id);
        $cacheEnabled = $params->has('rdr_disable_cache') ? !$params->get('rdr_disable_cache') : true;
        return $this->render('workqueue/participant.html.twig', [
            'participant' => $participant,
            'cacheEnabled' => $cacheEnabled,
            'orders' => $orders,
            'measurements' => $measurements,
            'problems' => $problems,
            'displayPatientStatusBlock' => false,
            'readOnly' => true,
            'biobankView' => true
        ]);
    }

    /**
     * @Route("/consents", name="workqueue_consents")
     */
    public function consentsAction(Request $request)
    {
        if (!$this->displayParticipantConsentsTab) {
            throw $this->createNotFoundException();
        }
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
        $filters = WorkQueue::$consentFilters;

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
            $participants = $this->workQueueService->participantSummarySearch($awardee, $params, 'wQTable', WorkQueue::$consentSortColumns);
            $ajaxData = [];
            $ajaxData['recordsTotal'] = $ajaxData['recordsFiltered'] = $this->workQueueService->getTotal();
            $ajaxData['data'] = $this->workQueueService->generateConsentTableRows($participants);
            $responseCode = 200;
            if ($this->workQueueService->isRdrError()) {
                $responseCode = 500;
            }
            return $this->json($ajaxData, $responseCode);
        } else {
            $params['exportType'] = 'consents';
            return $this->render('workqueue/consents.html.twig', [
                'filters' => $filters,
                'advancedFilters' => WorkQueue::$consentAdvanceFilters,
                'surveys' => WorkQueue::$surveys,
                'samples' => WorkQueue::$samples,
                'digitalHealthSharingTypes' => WorkQueue::$digitalHealthSharingTypes,
                'participants' => [],
                'params' => $params,
                'awardee' => $awardee,
                'isRdrError' => $this->workQueueService->isRdrError(),
                'samplesAlias' => WorkQueue::$samplesAlias,
                'canExport' => $this->workQueueService->canExport(),
                'exportConfiguration' => $this->workQueueService->getExportConfiguration(),
                'columnsDef' => WorkQueue::$columnsDef
            ]);
        }
    }
}
