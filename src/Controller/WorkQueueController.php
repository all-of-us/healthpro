<?php

namespace App\Controller;

use App\Audit\Log;
use App\Entity\Measurement;
use App\Entity\Order;
use App\Entity\Problem;
use App\Entity\WorkqueueView;
use App\Form\WorkQueueViewDeleteType;
use App\Form\WorkQueueViewType;
use App\Form\WorkQueueViewUpdateType;
use App\Helper\WorkQueue;
use App\Service\LoggerService;
use App\Service\OrderService;
use App\Service\ParticipantSummaryService;
use App\Service\SiteService;
use App\Service\WorkQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/workqueue')]
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

    #[Route(path: '/', name: 'workqueue_index')]
    #[Route(path: '/customized-view/{viewId}', name: 'workqueue_customized_view')]
    public function index(): Response
    {
        $workQueueView = $this->em->getRepository(WorkqueueView::class)->findOneBy([
            'user' => $this->getUserEntity(),
            'defaultView' => 1
        ]);

        if ($workQueueView) {
            $params = array_merge($workQueueView->getFiltersArray(), ['viewId' => $workQueueView->getId()]);
            $redirectUrl = $this->generateUrl('workqueue_customized_view', $params);
            return $this->redirect($redirectUrl);
        }
        return $this->redirectToRoute('workqueue_main');
    }

    #[Route(path: '/main', name: 'workqueue_main')]
    #[Route(path: '/customized-view/{viewId}', name: 'workqueue_customized_view')]
    public function mainAction(Request $request, $viewId = null): Response
    {
        if ($viewId) {
            $workQueueView = $this->em->getRepository(WorkqueueView::class)->findOneBy([
                'user' => $this->getUserEntity(),
                'id' => $viewId
            ]);
            if ($workQueueView === null) {
                throw $this->createAccessDeniedException();
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
            return $this->redirectToRoute('workqueue_main');
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
            $participants = $this->workQueueService->participantSummarySearch(
                $awardee,
                $params,
                'wQTable',
                WorkQueue::$sortColumns,
                $sites
            );
            $ajaxData = [];
            $ajaxData['recordsTotal'] = $ajaxData['recordsFiltered'] = $this->workQueueService->getTotal();
            $ajaxData['data'] = $this->workQueueService->generateTableRows($participants);
            $responseCode = 200;
            if ($this->workQueueService->isRdrError()) {
                $responseCode = 500;
            }
            return $this->json($ajaxData, $responseCode);
        }
        if (!$this->requestStack->getSession()->has('workQueueColumns')) {
            $this->requestStack->getSession()->set('workQueueColumns', WorkQueue::getWorkQueueColumns());
        }
        if ($viewId) {
            $workQueueViewColumns = json_decode($workQueueView->getColumns(), true);
            $this->requestStack->getSession()->set('workQueueViewColumns', $workQueueViewColumns);
        }
        return $this->render('workqueue/index.html.twig', [
            'filters' => $filters,
            'advancedFilters' => $advancedFilters,
            'surveys' => WorkQueue::$surveys,
            'pedsFields' => WorkQueue::$pedsOnlyFields,
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
            'customFilterLabels' => WorkQueue::$customFilterLabels,
            'columnGroups' => WorkQueue::$columnGroups,
            'filterLabelOptionPairs' => WorkQueue::getFilterLabelOptionPairs($advancedFilters),
            'workQueueViewForm' => $this->createForm(WorkQueueViewType::class)->createView(),
            'workQueueViews' => $this->em->getRepository(WorkqueueView::class)->findBy(['user' =>
                $this->getUserEntity()], ['defaultView' => 'desc', 'id' => 'desc']),
            'workQueueView' => $workQueueView ?? null,
            'workQueueViewDeleteForm' => $this->createForm(WorkQueueViewDeleteType::class)->createView(),
            'workQueueViewUpdateForm' => $this->createForm(WorkQueueViewUpdateType::class)->createView(),
        ]);
    }

    #[Route(path: '/export', name: 'workqueue_export')]
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

        $sites = $this->siteService->getAwardeeSites($awardee);

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
            if ($exportType === 'main' || $exportType === 'custom') {
                $columns = $exportType === 'custom' ? 'workQueueViewColumns' : 'workQueueColumns';
                $workQueueColumns = $this->requestStack->getSession()->get($columns);
                $exportHeaders = WorkQueue::getSessionExportHeaders($workQueueColumns);
            } else {
                $exportHeaders = WorkQueue::getExportHeaders();
            }
            $exportRowMethod = 'generateExportRow';
            $fileName = 'workqueue';
        }
        $stream = function () use (
            $params,
            $awardee,
            $limit,
            $pageSize,
            $exportHeaders,
            $exportRowMethod,
            $workQueueColumns,
            $sites
        ) {
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
                $participants = $this->workQueueService->participantSummarySearch($awardee, $params, null, null, $sites);
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


    #[Route(path: '/participant/{id}', name: 'workqueue_participant')]
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

    #[Route(path: '/consents', name: 'workqueue_consents')]
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
                $participants = $this->workQueueService->participantSummarySearch(
                    $awardee,
                    $params,
                    'wQTable',
                    WorkQueue::$consentSortColumns,
                    $sites
                );
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
        }
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
                $this->getUserEntity()], ['defaultView' => 'desc', 'id' => 'desc']),
            'workQueueViewDeleteForm' => $this->createForm(WorkQueueViewDeleteType::class)->createView()
        ]);
    }

    #[Route(path: '/consent/columns', name: 'workqueue_consent_columns')]
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

    #[Route(path: '/columns', name: 'workqueue_columns')]
    public function columnsAction(Request $request)
    {
        $columns = $request->query->has('columnType') ? 'workQueueViewColumns' : 'workQueueColumns';
        if ($request->query->has('select')) {
            $this->requestStack->getSession()->set($columns, WorkQueue::getWorkQueueAllColumns());
            return $this->json(['success' => true]);
        }
        if ($request->query->has('deselect')) {
            $this->requestStack->getSession()->set($columns, WorkQueue::$defaultColumns);
            return $this->json(['success' => true]);
        }
        if ($request->query->has('groupName')) {
            $this->requestStack->getSession()->set($columns, WorkQueue::getWorkQueueGroupColumns($request->query->get('groupName')));
            return $this->json(['success' => true]);
        }
        $workQueueColumns = $this->requestStack->getSession()->get($columns);
        $columnName = $request->query->get('columnName');
        if ($request->query->get('checked') === 'true') {
            $workQueueColumns[] = $columnName;
        } else {
            if (($key = array_search($columnName, $workQueueColumns)) !== false) {
                unset($workQueueColumns[$key]);
            }
        }
        $this->requestStack->getSession()->set($columns, $workQueueColumns);
        return $this->json(['success' => true]);
    }

    #[Route(path: '/participant/{id}/consent-histories/{type}', name: 'workqueue_consent_histories')]
    public function consentHistories($id, $type, ParticipantSummaryService $participantSummaryService)
    {
        $participant = $participantSummaryService->getParticipantById($id);
        return $this->render('workqueue/partials/consent-modal.html.twig', [
            'consentType' => $type,
            'participant' => $participant,
            'consentStatusDisplayText' => WorkQueue::$consentStatusDisplayText
        ]);
    }

    #[Route(path: '/view/delete', name: 'workqueue_view_delete', methods: ['POST'])]
    public function workQueueViewDeleteAction(Request $request): Response
    {
        $workQueueViewDeleteForm = $this->createForm(WorkQueueViewDeleteType::class);
        $workQueueViewDeleteForm->handleRequest($request);
        if ($workQueueViewDeleteForm->isSubmitted() && $workQueueViewDeleteForm->isValid()) {
            $workQueueViewId = $workQueueViewDeleteForm['id']->getData();
            $workQueueView = $this->em->getRepository(WorkqueueView::class)->findOneBy([
                'id' => $workQueueViewId,
                'user' => $this->getUserEntity()
            ]);
            if ($workQueueView) {
                $this->em->remove($workQueueView);
                $this->em->flush();
                $this->addFlash('success', 'Work Queue view deleted');
            } else {
                $this->addFlash('error', 'Error deleting view. Please try again');
            }
        }
        if ($request->query->get('viewType') === 'custom') {
            // Redirect to the main workqueue if user delete the same view that they are in
            if (isset($workQueueViewId) && $request->query->get('currentViewId') === $workQueueViewId) {
                return $this->redirectToRoute('workqueue_main');
            }
            return $this->redirect($request->query->get('currentUrl'));
        }
        $route = $request->query->get('viewType') === 'consent' ? 'workqueue_consents' : 'workqueue_main';
        return $this->redirectToRoute($route);
    }

    #[Route(path: '/view/update', name: 'workqueue_view_update', methods: ['POST'])]
    public function workQueueViewUpdateAction(Request $request): Response
    {
        $workQueueViewUpdateForm = $this->createForm(WorkQueueViewUpdateType::class);
        $workQueueViewUpdateForm->handleRequest($request);
        if ($workQueueViewUpdateForm->isSubmitted() && $workQueueViewUpdateForm->isValid()) {
            $workQueueViewId = $workQueueViewUpdateForm['id']->getData();
            $workQueueView = $this->em->getRepository(WorkqueueView::class)->findOneBy([
                'id' => $workQueueViewId,
                'user' => $this->getUserEntity()
            ]);
            if ($workQueueView) {
                $this->updateWorkQueueView($workQueueView, $request->query->get('viewType'), $request->query->get('params'));
                $this->addFlash('success', 'Work Queue view updated');
            } else {
                $this->addFlash('error', 'Error updating view. Please try again');
            }
            $params = array_merge($workQueueView->getFiltersArray(), ['viewId' => $workQueueView->getId()]);
            $redirectUrl = $this->generateUrl('workqueue_customized_view', $params);
            return $this->redirect($redirectUrl);
        }
        return $this->redirect('workqueue_main');
    }

    #[Route(path: '/view/{id}', name: 'workqueue_view', defaults: ['id' => null])]
    public function workQueueViewAction($id, Request $request): Response
    {
        if ($id) {
            $workQueueView = $this->em->getRepository(WorkqueueView::class)->findOneBy([
                'id' => $id,
                'user' => $this->getUserEntity()
            ]);
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
                    $this->updateWorkQueueView($workQueueView, $request->query->get('viewType'), $request->query->get('params'));
                    $this->addFlash('success', 'Work Queue view saved');
                    $params = array_merge($workQueueView->getFiltersArray(), ['viewId' => $workQueueView->getId()]);
                    $redirectUrl = $this->generateUrl('workqueue_customized_view', $params);
                    return $this->redirect($redirectUrl);
                }
            } else {
                $this->addFlash('error', 'Invalid form');
            }
            if ($request->query->get('viewType') === 'custom') {
                return $this->redirect($request->query->get('currentUrl'));
            }
            $route = $request->query->get('viewType') === 'consent' ? 'workqueue_consents' : 'workqueue_main';
            return $this->redirectToRoute($route);
        }
        return $this->render('workqueue/partials/save-view-modal.html.twig', [
            'workQueueViewForm' => $workQueueViewForm->createView(),
            'workQueueViewId' => $id,
            'viewType' => $request->query->get('viewType'),
            'currentUrl' => $request->query->get('currentUrl')
        ]);
    }

    #[Route(path: '/view/change/default/{id}', name: 'workqueue_view_change_default')]
    public function workQueueViewChangeDefaultAction($id, Request $request): Response
    {
        $workQueueView = $this->em->getRepository(WorkqueueView::class)->findOneBy([
            'id' => $id,
            'user' => $this->getUserEntity()
        ]);
        if (!$workQueueView) {
            throw $this->createNotFoundException('Work Queue view not found.');
        }
        if ($request->query->get('checked') === 'true') {
            $workQueueView->setDefaultView(1);
        } else {
            $workQueueView->setDefaultView(0);
        }
        $this->em->persist($workQueueView);
        $this->em->flush();

        $this->em->getRepository(WorkqueueView::class)->updateDefaultView(
            $workQueueView->getId(),
            $this->getUserEntity()
        );

        return $this->json(['success' => true]);
    }

    #[Route(path: '/view/check/name/{id}', name: 'workqueue_view_check_name', defaults: ['id' => null])]
    public function workQueueViewCheckNameAction($id, Request $request): Response
    {
        $workQueueView = $this->em->getRepository(WorkqueueView::class)
            ->checkDuplicateName($id, $request->query->get('name'), $this->getUserEntity());
        $status = $workQueueView ? 1 : 0;
        return $this->json(['success' => true, 'status' => $status]);
    }

    private function updateWorkQueueView($workQueueView, $viewType, $params): void
    {
        $columnsType = $workQueueView->getColumnsType($viewType);
        $workQueueView->setColumns(json_encode($this->requestStack->getSession()->get($columnsType)));
        if ($params) {
            $workQueueView->setFilters(json_encode($params));
        }
        $this->em->persist($workQueueView);
        $this->em->flush();
    }
}
