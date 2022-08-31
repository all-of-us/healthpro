<?php

namespace App\Controller;

use App\Entity\Measurement;
use App\Entity\Order;
use App\Entity\Problem;
use App\Entity\WorkqueueView;
use App\Form\WorkQueueParticipantLookupIdType;
use App\Form\WorkQueueParticipantLookupSearchType;
use App\Form\WorkQueueViewDeleteType;
use App\Form\WorkQueueViewType;
use App\Service\LoggerService;
use App\Service\OrderService;
use App\Service\ParticipantSummaryService;
use App\Service\SiteService;
use App\Service\WorkQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Helper\WorkQueue;
use App\Audit\Log;

/**
 * @Route("/workqueue")
 */
class WorkQueueController extends BaseController
{
    protected $requestStack;
    protected $workQueueService;
    protected $siteService;
    protected $displayParticipantConsentsTab;

    public function __construct(
        RequestStack $requestStack,
        WorkQueueService $workQueueService,
        SiteService $siteService,
        ParameterBagInterface $params,
        EntityManagerInterface $em
    ) {
        parent::__construct($em);
        $this->requestStack = $requestStack;
        $this->workQueueService = $workQueueService;
        $this->siteService = $siteService;
        $this->displayParticipantConsentsTab = $params->has('feature.participantconsentsworkqueue') ? $params->get('feature.participantconsentsworkqueue') : false;
    }

    /**
     * @Route("/", name="workqueue_index")
     * @Route("/customized-view/{viewId}", name="workqueue_customized_view")
     */
    public function index(Request $request, $viewId = null)
    {
        if ($viewId) {
            $workQueueView = $this->em->getRepository(WorkqueueView::class)->findOneBy([
                'user' => $this->getUserEntity(),
                'id' => $viewId
            ]);
            if ($workQueueView === null) {
                return $this->createAccessDeniedException();
            }
        }
        if ($this->isGranted('ROLE_USER')) {
            $awardee = $this->siteService->getSiteAwardee();
        }
        $awardees = [];
        if ($this->isGranted('ROLE_AWARDEE')) {
            $awardees = $this->siteService->getSuperUserAwardees();
            if (!empty($awardees)) {
                if (($sessionAwardee = $this->requestStack->getSession()->get('awardeeOrganization')) && in_array($sessionAwardee, $awardees)) {
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
        if ($request->query->has('reset')) {
            $this->requestStack->getSession()->set('workQueueColumns', WorkQueue::getWorkQueueColumns());
            return $this->redirectToRoute('workqueue_index');
        }

        $params = array_filter($request->query->all());

        $filters = [];
        $advancedFilters = WorkQueue::$consentAdvanceFilters;

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
            // Save selected (or default) awardee in requestStack->getSession()
            $this->requestStack->getSession()->set('workQueueAwardee', $awardee);

            // Remove patient status from advanced filter for awardee
            unset($advancedFilters['Status']['patientStatus']);
        }

        $sites = $this->siteService->getAwardeeSites($awardee);
        if (!empty($sites)) {
            //Add sites filter
            $sitesList = [];
            $sitesList['site']['label'] = 'Paired Site';
            $sitesList['site']['options']['View All'] = '';
            foreach ($sites as $site) {
                if (!empty($site->getGoogleGroup())) {
                    $sitesList['site']['options'][$site->getName()] = $site->getGoogleGroup();
                }
            }
            $sitesList['site']['options']['Unpaired'] = 'UNSET';
            $advancedFilters['Pairing'] = array_merge($advancedFilters['Pairing'], $sitesList);

            //Add organization filter
            $organizationsList = [];
            $organizationsList['organization_id']['label'] = 'Paired Organization';
            $organizationsList['organization_id']['options']['View All'] = '';
            foreach ($sites as $site) {
                if (!empty($site->getOrganizationId())) {
                    $organizationsList['organization_id']['options'][$this->siteService->getOrganizationDisplayName($site->getOrganizationId())] = $site->getOrganizationId();
                }
            }
            $organizationsList['organization_id']['options']['Unpaired'] = 'UNSET';
            $advancedFilters['Pairing'] = array_merge($advancedFilters['Pairing'], $organizationsList);
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
            if (!$this->requestStack->getSession()->has('workQueueColumns')) {
                $this->requestStack->getSession()->set('workQueueColumns', WorkQueue::getWorkQueueColumns());
            }
            if ($viewId) {
                $workQueueViewColumns = json_decode($workQueueView->getColumns(), true);
                $this->requestStack->getSession()->set('workQueueColumns', $workQueueViewColumns);
            }
            return $this->render('workqueue/index.html.twig', [
                'filters' => $filters,
                'advancedFilters' => $advancedFilters,
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
                'displayParticipantConsentsTab' => $this->displayParticipantConsentsTab,
                'columns' => WorkQueue::$columns,
                'columnsDef' => WorkQueue::$columnsDef,
                'filterIcons' => WorkQueue::$filterIcons,
                'columnGroups' => WorkQueue::$columnGroups,
                'filterLabelOptionPairs' => WorkQueue::getFilterLabelOptionPairs($advancedFilters),
                'workQueueViewForm' => $this->createForm(WorkQueueViewType::class)->createView(),
                'workQueueViews' => $this->em->getRepository(WorkqueueView::class)->findBy(['user' =>
                    $this->getUserEntity()], ['id' => 'desc']),
                'workQueueViewDeleteForm' => $this->createForm(WorkQueueViewDeleteType::class)->createView()
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
            $awardee = $this->requestStack->getSession()->get('workQueueAwardee');
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
        $exportType = isset($params['exportType']) ? $params['exportType'] : '';
        $workQueueColumns = [];
        if ($this->displayParticipantConsentsTab && $exportType === 'consents') {
            $workQueueColumns = $this->requestStack->getSession()->get('workQueueConsentColumns');
            $exportHeaders = WorkQueue::getConsentExportHeaders($workQueueColumns);
            $exportRowMethod = 'generateConsentExportRow';
            $fileName = 'workqueue_consents';
        } else {
            if ($exportType === 'main') {
                $workQueueColumns = $this->requestStack->getSession()->get('workQueueColumns');
                $exportHeaders = WorkQueue::getSessionExportHeaders($workQueueColumns);
            } else {
                $exportHeaders = WorkQueue::getExportHeaders();
            }
            $exportRowMethod = 'generateExportRow';
            $fileName = 'workqueue';
        }
        $stream = function () use ($params, $awardee, $limit, $pageSize, $exportHeaders, $exportRowMethod, $workQueueColumns) {
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
                    fputcsv($output, $this->workQueueService->$exportRowMethod($participant, $workQueueColumns));
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

        $measurements = $this->em->getRepository(Measurement::class)->findBy(['participantId' => $id]);

        // Internal Orders
        $orders = $this->em->getRepository(Order::class)->findBy(['participantId' => $id]);

        // Quanum Orders
        $order = new Order();
        $orderService->loadSamplesSchema($order);
        $quanumOrders = $orderService->getOrdersByParticipant($participant->id);
        foreach ($quanumOrders as $quanumOrder) {
            if (in_array($quanumOrder->origin, ['careevolution'])) {
                $orders[] = $orderService->loadFromJsonObject($quanumOrder);
            }
        }

        $problems = $this->em->getRepository(Problem::class)->getProblemsWithCommentsCount($id);
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
        $awardees = [];
        if ($this->isGranted('ROLE_AWARDEE')) {
            $awardees = $this->siteService->getSuperUserAwardees();
            if (!empty($awardees)) {
                if (($sessionAwardee = $this->requestStack->getSession()->get('awardeeOrganization')) && in_array($sessionAwardee, $awardees)) {
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
        if ($request->query->has('reset')) {
            $this->requestStack->getSession()->set('workQueueConsentColumns', WorkQueue::getWorkQueueConsentColumns());
            return $this->redirectToRoute('workqueue_consents');
        }

        $params = array_filter($request->query->all());
        $filters = [];
        $consentAdvanceFilters = WorkQueue::$consentAdvanceFilters;

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
            $this->requestStack->getSession()->set('workQueueAwardee', $awardee);

            // Remove patient status from advanced filter for awardee
            unset($consentAdvanceFilters['Status']['patientStatus']);
        }

        $sites = $this->siteService->getAwardeeSites($awardee);
        if (!empty($sites)) {
            //Add sites filter
            $sitesList = [];
            $sitesList['site']['label'] = 'Paired Site';
            $sitesList['site']['options']['View All'] = '';
            foreach ($sites as $site) {
                if (!empty($site->getGoogleGroup())) {
                    $sitesList['site']['options'][$site->getName()] = $site->getGoogleGroup();
                }
            }
            $sitesList['site']['options']['Unpaired'] = 'UNSET';
            $consentAdvanceFilters['Pairing'] = array_merge($consentAdvanceFilters['Pairing'], $sitesList);

            //Add organization filter
            $organizationsList = [];
            $organizationsList['organization_id']['label'] = 'Paired Organization';
            $organizationsList['organization_id']['options']['View All'] = '';
            foreach ($sites as $site) {
                if (!empty($site->getOrganizationId())) {
                    $organizationsList['organization_id']['options'][$this->siteService->getOrganizationDisplayName($site->getOrganizationId())] = $site->getOrganizationId();
                }
            }
            $organizationsList['organization_id']['options']['Unpaired'] = 'UNSET';
            $consentAdvanceFilters['Pairing'] = array_merge($consentAdvanceFilters['Pairing'], $organizationsList);
        }

        //For ajax requests
        if ($request->isXmlHttpRequest()) {
            if (WorkQueue::isValidDates($params)) {
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
            }
            $ajaxData = [];
            $ajaxData['recordsTotal'] = 0;
            $ajaxData['data'] = [];
            return $this->json($ajaxData, 200);
        } else {
            $params['exportType'] = 'consents';
            if (!$this->requestStack->getSession()->has('workQueueConsentColumns')) {
                $workQueueConsentColumns = WorkQueue::getWorkQueueConsentColumns();
                $this->requestStack->getSession()->set('workQueueConsentColumns', $workQueueConsentColumns);
            }
            return $this->render('workqueue/consents.html.twig', [
                'filters' => $filters,
                'advancedFilters' => $consentAdvanceFilters,
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
                'columnsDef' => WorkQueue::$columnsDef,
                'consentColumns' => WorkQueue::$consentColumns,
                'filterIcons' => WorkQueue::$filterIcons,
                'filterLabelOptionPairs' => WorkQueue::getFilterLabelOptionPairs($consentAdvanceFilters),
                'workQueueViewForm' => $this->createForm(WorkQueueViewType::class)->createView(),
                'workQueueViews' => $this->em->getRepository(WorkqueueView::class)->findBy(['user' =>
                    $this->getUserEntity()], ['id' => 'desc']),
                'workQueueViewDeleteForm' => $this->createForm(WorkQueueViewDeleteType::class)->createView()
            ]);
        }
    }

    /**
     * @Route("/consent/columns", name="workqueue_consent_columns")
     */
    public function consentColumnsAction(Request $request)
    {
        if (!$this->displayParticipantConsentsTab) {
            throw $this->createNotFoundException();
        }
        if ($request->query->has('select')) {
            $this->requestStack->getSession()->set('workQueueConsentColumns', WorkQueue::getWorkQueueConsentColumns());
            return $this->json(['success' => true]);
        }
        if ($request->query->has('deselect')) {
            $this->requestStack->getSession()->set('workQueueConsentColumns', WorkQueue::$defaultConsentColumns);
            return $this->json(['success' => true]);
        }
        $workQueueConsentColumns = $this->requestStack->getSession()->get('workQueueConsentColumns');
        $columnName = $request->query->get('columnName');
        if ($request->query->get('checked') === 'true') {
            $workQueueConsentColumns[] = $columnName;
        } else {
            if (($key = array_search($columnName, $workQueueConsentColumns)) !== false) {
                unset($workQueueConsentColumns[$key]);
            }
        }
        $this->requestStack->getSession()->set('workQueueConsentColumns', $workQueueConsentColumns);
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/columns", name="workqueue_columns")
     */
    public function columnsAction(Request $request)
    {
        if ($request->query->has('select')) {
            $this->requestStack->getSession()->set('workQueueColumns', WorkQueue::getWorkQueueColumns());
            return $this->json(['success' => true]);
        }
        if ($request->query->has('deselect')) {
            $this->requestStack->getSession()->set('workQueueColumns', WorkQueue::$defaultColumns);
            return $this->json(['success' => true]);
        }
        if ($request->query->has('groupName')) {
            $this->requestStack->getSession()->set('workQueueColumns', WorkQueue::getWorkQueueGroupColumns($request->query->get('groupName')));
            return $this->json(['success' => true]);
        }
        $workQueueColumns = $this->requestStack->getSession()->get('workQueueColumns');
        $columnName = $request->query->get('columnName');
        if ($request->query->get('checked') === 'true') {
            $workQueueColumns[] = $columnName;
        } else {
            if (($key = array_search($columnName, $workQueueColumns)) !== false) {
                unset($workQueueColumns[$key]);
            }
        }
        $this->requestStack->getSession()->set('workQueueColumns', $workQueueColumns);
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/participant/{id}/consent-histories/{type}", name="workqueue_consent_histories")
     */
    public function consentHistories($id, $type, ParticipantSummaryService $participantSummaryService)
    {
        $participant = $participantSummaryService->getParticipantById($id);
        return $this->render('workqueue/partials/consent-modal.html.twig', [
            'consentType' => $type,
            'participant' => $participant,
            'consentStatusDisplayText' => WorkQueue::$consentStatusDisplayText
        ]);
    }

    /**
     * @Route("/view/delete", name="workqueue_view_delete", methods={"POST"})
     */
    public function workQueueViewDeleteAction(Request $request)
    {
        $workQueueViewDeleteForm = $this->createForm(WorkQueueViewDeleteType::class);
        $workQueueViewDeleteForm->handleRequest($request);
        if ($workQueueViewDeleteForm->isSubmitted() && $workQueueViewDeleteForm->isValid()) {
            $workQueueViewId = $workQueueViewDeleteForm['id']->getData();
            $workQueueView = $this->em->getRepository(WorkqueueView::class)->find($workQueueViewId);
            if ($workQueueView) {
                $this->em->remove($workQueueView);
                $this->em->flush();
                $this->addFlash('success', 'Work Queue view deleted');
            } else {
                $this->addFlash('error', 'Error deleting view. Please try again');
            }
        }
        $route = $request->query->get('viewType') === 'consent' ? 'workqueue_consents' : 'workqueue_index';
        return $this->redirectToRoute($route);
    }

    /**
     * @Route("/view/{id}", name="workqueue_view", defaults={"id": null})
     */
    public function workQueueViewAction($id, Request $request)
    {
        if ($id) {
            $workQueueView = $this->em->getRepository(WorkqueueView::class)->find($id);
            if (!$workQueueView) {
                throw $this->createNotFoundException('Work Queue view not found.');
            }
        } else {
            $workQueueView = new WorkqueueView();
        }

        $workQueueViewForm = $this->createForm(WorkQueueViewType::class, $workQueueView);
        $workQueueViewForm->handleRequest($request);
        if ($workQueueViewForm->isSubmitted()) {
            if ($workQueueViewForm->isValid()) {
                if ($id) {
                    $this->em->persist($workQueueView);
                    $this->em->flush();
                    $this->addFlash('success', 'Work Queue view updated');
                } else {
                    $workQueueView->setUser($this->getUserEntity());
                    $workQueueView->setCreatedTs(new \DateTime());
                    $type = $request->query->get('viewType') === 'consent' ? 'consent' : 'main';
                    $columnType = $type === 'main' ? 'workQueueColumns' : 'workQueueConsentColumns';
                    $workQueueView->setType($type);
                    $workQueueView->setColumns(json_encode($this->requestStack->getSession()->get($columnType)));
                    if ($request->query->get('params')) {
                        $workQueueView->setFilters(json_encode($request->query->get('params')));
                    }
                    $this->em->persist($workQueueView);
                    $this->em->flush();
                    $this->addFlash('success', 'Work Queue view saved');
                    $redirectUrl = $this->generateUrl('workqueue_customized_view', [
                            'viewId' => $workQueueView->getId()]) . '?' . $workQueueView->getFiltersQueryParams();
                    return $this->redirect($redirectUrl);
                }
                if ($workQueueViewForm->get('defaultView')->getData()) {
                    $this->em->getRepository(WorkqueueView::class)->updateDefaultView(
                        $workQueueView->getId(),
                        $this->getUserEntity()
                    );
                }
            } else {
                $this->addFlash('error', 'Invalid form');
            }
            $route = $request->query->get('viewType') === 'consent' ? 'workqueue_consents' : 'workqueue_index';
            return $this->redirectToRoute($route);
        }
        return $this->render('workqueue/partials/save-view-modal.html.twig', [
            'workQueueViewForm' => $workQueueViewForm->createView(),
            'workQueueViewId' => $id,
            'viewType' => $request->query->get('viewType')
        ]);
    }
}
