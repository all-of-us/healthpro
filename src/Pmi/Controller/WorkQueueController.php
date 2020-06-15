<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Pmi\Audit\Log;
use Pmi\Entities\Participant;
use Pmi\Drc\CodeBook;
use Symfony\Component\HttpFoundation\JsonResponse;
use Pmi\WorkQueue\WorkQueue;
use Pmi\Order\Order;

class WorkQueueController extends AbstractController
{
    protected static $name = 'workqueue';
    protected static $routes = [
        ['index', '/', ['method' => 'GET|POST']],
        ['export', '/export.csv'],
        ['participant', '/participant/{id}']
    ];

    protected $rdrError = false;

    protected function participantSummarySearch($organization, &$params, $app, $type = null)
    {
        $rdrParams = [];
        $next = true;

        if ($type == 'wQTable') {
            $rdrParams['_count'] = isset($params['length']) ? $params['length'] : 10;
            $rdrParams['_offset'] = isset($params['start']) ? $params['start'] : 0;

            // Pass sort params
            if (!empty($params['order'][0])) {
                $sortColumnIndex = $params['order'][0]['column'];
                $sortColumnName = WorkQueue::$sortColumns[$sortColumnIndex];
                $sortDir = $params['order'][0]['dir'];
                if ($sortDir == 'asc') {
                    $rdrParams['_sort'] = $sortColumnName;
                } else {
                    $rdrParams['_sort:desc'] = $sortColumnName;
                }
            }

            // Set require next token to false
            $next = false;
        }

        // Unset other params when activity status is withdrawn
        if (isset($params['activityStatus']) && $params['activityStatus'] === 'withdrawn') {
            foreach ($params as $key => $value) {
                if ($key === 'activityStatus' || $key === 'organization') {
                    continue;
                }
                unset($params[$key]);
            }
        }
        $rdrParams['hpoId'] = $organization;

        //Pass export params
        if (isset($params['_count'])) {
            $rdrParams['_count'] = $params['_count'];
        }
        if (isset($params['_sort:desc'])) {
            $rdrParams['_sort:desc'] = $params['_sort:desc'];
        }

        //Pass filter params
        if (!empty($params['activityStatus'])) {
            if ($params['activityStatus'] === 'withdrawn') {
                $rdrParams['withdrawalStatus'] = 'NO_USE';
            } else {
                $rdrParams['withdrawalStatus'] = 'NOT_WITHDRAWN';
                if ($params['activityStatus'] === 'active') {
                    $rdrParams['suspensionStatus'] = 'NOT_SUSPENDED';
                } elseif ($params['activityStatus'] === 'deactivated') {
                    $rdrParams['suspensionStatus'] = 'NO_CONTACT';
                }
            }
        }
        if (!empty($params['enrollmentStatus'])) {
            $rdrParams['enrollmentStatus'] = $params['enrollmentStatus'];
        }
        if (!empty($params['consentForElectronicHealthRecords'])) {
            $rdrParams['consentForElectronicHealthRecords'] = $params['consentForElectronicHealthRecords'];
        }
        if (!empty($params['consentForGenomicsROR'])) {
            $rdrParams['consentForGenomicsROR'] = $params['consentForGenomicsROR'];
        }
        if (!empty($params['genderIdentity'])) {
            $rdrParams['genderIdentity'] = $params['genderIdentity'];
        }
        if (!empty($params['race'])) {
            $rdrParams['race'] = $params['race'];
        }
        if (!empty($params['participantOrigin'])) {
            $rdrParams['participantOrigin'] = $params['participantOrigin'];
        }
        if (!empty($params['consentCohort'])) {
            $rdrParams['consentCohort'] = $params['participantOrigin'];
        }
        // Add site prefix
        if (!empty($params['site'])) {
            $site = $params['site'];
            if ($site !== 'UNSET') {
                $site = \Pmi\Security\User::SITE_PREFIX . $site;
            }
            $rdrParams['site'] = $site;
        }
        if (!empty($params['organization_id'])) {
            $rdrParams['organization'] = $params['organization_id'];
        }
        // Patient status query parameter format Organization:Status
        if (!empty($params['patientStatus']) && !empty($params['siteOrganizationId'])) {
            $rdrParams['patientStatus'] = $params['siteOrganizationId'] . ':' . $params['patientStatus'];
        }

        // convert age range to dob filters - using string instead of array to support multiple params with same name
        if (isset($params['ageRange'])) {
            $ageRange = $params['ageRange'];
            $rdrParams = http_build_query($rdrParams, null, '&', PHP_QUERY_RFC3986);

            $dateOfBirthFilters = CodeBook::ageRangeToDob($ageRange);
            foreach ($dateOfBirthFilters as $filter) {
                $rdrParams .= '&dateOfBirth=' . rawurlencode($filter);
            }
        }
        $results = [];
        try {
            $summaries = $app['pmi.drc.participants']->listParticipantSummaries($rdrParams, $next);
            foreach ($summaries as $summary) {
                if (isset($summary->resource)) {
                    $results[] = new Participant($summary->resource);
                }
            }
        } catch (\Exception $e){
            $this->rdrError = true;
        }
        return $results;
    }

    private function getExportConfiguration(Application $app)
    {
        return [
            'limit' => $app->getConfig('workqueue_export_limit') ?: WorkQueue::LIMIT_EXPORT,
            'pageSize' => $app->getConfig('workqueue_export_page_size') ?: WorkQueue::LIMIT_EXPORT_PAGE_SIZE
        ];
    }

    public function indexAction(Application $app, Request $request)
    {
        if ($app->hasRole('ROLE_USER')) {
            $organization = $app->getSiteOrganization();
        }
        if ($app->hasRole('ROLE_AWARDEE')) {
            $organizations = $app->getAwardeeOrganization();
            if (!empty($organizations)) {
                if (($sessionOrganization = $app['session']->get('awardeeOrganization')) && in_array($sessionOrganization, $organizations)) {
                    $organization = $sessionOrganization;
                } else {
                    // Default to first organization
                    $organization = $organizations[0];
                }
            }
        }
        if (empty($organization)) {
            return $app['twig']->render('workqueue/no-organization.html.twig');
        }

        $params = array_filter($request->query->all());
        $filters = WorkQueue::$filters;

        if ($app->hasRole('ROLE_AWARDEE')) {
            // Add organizations to filters
            // ToDo: change organization key and variable names to awardee
            $organizationsList = [];
            $organizationsList['organization']['label'] = 'Organization';
            foreach ($organizations as $org) {
                $organizationsList['organization']['options'][$app->getAwardeeDisplayName($org)] = $org;
            }
            $filters = array_merge($filters, $organizationsList);

            // Set to selected organization
            if (isset($params['organization'])) {
                // Check if the awardee has access to this organization
                if (!in_array($params['organization'], $app->getAwardeeOrganization())) {
                    $app->abort(403);
                }
                $organization = $params['organization'];
                unset($params['organization']);
            }
            // Save selected (or default) organization in session
            $app['session']->set('awardeeOrganization', $organization);

            // Remove patient status filter for awardee
            unset($filters['patientStatus']);
        }

        // Display current organization in the default patient status filter drop down label
        if (isset($filters['patientStatus'])) {
            $filters['patientStatus']['label'] = 'Patient Status at ' . $app->getOrganizationDisplayName($app->getSiteOrganizationId());
        }

        $sites = $app->getSitesFromOrganization($organization);
        if (!empty($sites)) {
            //Add sites filter
            $sitesList = [];
            $sitesList['site']['label'] = 'Paired Site';
            foreach ($sites as $site) {
                if (!empty($site['google_group'])) {
                    $sitesList['site']['options'][$site['name']] = $site['google_group'];
                }
            }
            $sitesList['site']['options']['Unpaired'] = 'UNSET';
            $filters = array_merge($filters, $sitesList);

            //Add organization filter
            $organizationsList = [];
            $organizationsList['organization_id']['label'] = 'Paired Organization';
            foreach ($sites as $site) {
                if (!empty($site['organization_id'])) {
                    $organizationsList['organization_id']['options'][$app->getOrganizationDisplayName($site['organization_id'])] = $site['organization_id'];
                }
            }
            if (!empty($organizationsList['organization_id']['options'])) {
                $filters = array_merge($filters, $organizationsList);
            }
        }

        //For ajax requests
        if ($request->isXmlHttpRequest()) {
            $params = array_merge($params, array_filter($request->request->all()));
            if (!empty($params['patientStatus'])) {
                $params['siteOrganizationId'] = $app->getSiteOrganizationId();
            }
            $participants = $this->participantSummarySearch($organization, $params, $app, $type = 'wQTable');
            $ajaxData = [];
            $ajaxData['recordsTotal'] = $ajaxData['recordsFiltered'] = $app['pmi.drc.participants']->getTotal();
            $WorkQueue = new WorkQueue;
            $ajaxData['data'] = $WorkQueue->generateTableRows($participants, $app);
            $responseCode = 200;
            if ($this->rdrError) {
                $responseCode = 500;
            }
            return new JsonResponse($ajaxData, $responseCode);
        } else {
            $siteWorkQueueDownload = $this->getSiteWorkQueueDownload($app);
            return $app['twig']->render('workqueue/index.html.twig', [
                'filters' => $filters,
                'surveys' => WorkQueue::$surveys,
                'samples' => WorkQueue::$samples,
                'participants' => [],
                'params' => $params,
                'organization' => $organization,
                'isRdrError' => $this->rdrError,
                'samplesAlias' => WorkQueue::$samplesAlias,
                'isDownloadDisabled' => $this->isDownloadDisabled($siteWorkQueueDownload),
                'exportConfiguration' => $this->getExportConfiguration($app)
            ]);
        }
    }

    public function exportAction(Application $app, Request $request)
    {
        $siteWorkQueueDownload = $this->getSiteWorkQueueDownload($app);
        if ($siteWorkQueueDownload === AdminController::DOWNLOAD_DISABLED) {
            $app->abort(403);
        }
        if ($app->hasRole('ROLE_AWARDEE')) {
            $organization = $app['session']->get('awardeeOrganization');
            $site = $app->getAwardeeId();
        } else {
            $organization = $app->getSiteOrganization();
            $site = $app->getSiteId();
        }
        if (!$organization) {
            return $app['twig']->render('workqueue/no-organization.html.twig');
        }

        $exportConfiguration = $this->getExportConfiguration($app);
        $limit = $exportConfiguration['limit'];
        $pageSize = $exportConfiguration['pageSize'];

        $hasFullDataAccess = $siteWorkQueueDownload === AdminController::FULL_DATA_ACCESS || $app->hasRole('ROLE_AWARDEE');

        $params = array_filter($request->query->all());
        $params['_count'] = $pageSize;
        $params['_sort:desc'] = 'consentForStudyEnrollmentAuthored';
        if (!empty($params['patientStatus'])) {
            $params['siteOrganizationId'] = $app->getSiteOrganizationId();
        }

        $stream = function() use ($app, $params, $organization, $hasFullDataAccess, $limit, $pageSize) {
            $output = fopen('php://output', 'w');
            // Add UTF-8 BOM
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['This file contains information that is sensitive and confidential. Do not distribute either the file or its contents.']);
            fwrite($output, "\"\"\n");
            if ($hasFullDataAccess) {
                $headers = [
                    'PMI ID',
                    'Biobank ID',
                    'Last Name',
                    'First Name',
                    'Date of Birth',
                    'Language',
                    'Participant Status',
                    'General Consent Status',
                    'General Consent Date',
                    'EHR Consent Status',
                    'EHR Consent Date',
                    'CABoR Consent Status',
                    'CABoR Consent Date',
                    'Withdrawal Status',
                    'Withdrawal Date',
                    'Street Address',
                    'City',
                    'State',
                    'ZIP',
                    'Email',
                    'Phone',
                    'Sex',
                    'Gender Identity',
                    'Race/Ethnicity',
                    'Education',
                    'Required PPI Surveys Complete',
                    'Completed Surveys'
                ];
                foreach (WorkQueue::$surveys as $survey => $label) {
                    if (in_array($survey, WorkQueue::$initialSurveys)) {
                        $headers[] = $label . ' PPI Survey Complete';
                        $headers[] = $label . ' PPI Survey Completion Date';
                    }
                }
            } else {
                $headers = [
                    'PMI ID',
                    'Biobank ID',
                    'ZIP'
                ];
            }
            $headers[] = 'Physical Measurements Status';
            $headers[] = 'Physical Measurements Completion Date';
            $headers[] = 'Paired Site';
            $headers[] = 'Paired Organization';
            $headers[] = 'Physical Measurements Site';
            $headers[] = 'Samples for DNA Received';
            $headers[] = 'Biospecimens';
            foreach (WorkQueue::$samples as $sample => $label) {
                $headers[] = $label . ' Received';
                $headers[] = $label . ' Received Date';
            }
            $headers[] = 'Biospecimens Site';
            $headers[] = 'Withdrawal Reason';
            $headers[] = 'Language of General Consent';
            $headers[] = 'DV-only EHR Sharing Status';
            $headers[] = 'DV-only EHR Sharing Date';
            if ($hasFullDataAccess) {
                $headers[] = 'Login Phone';
                $headers[] = 'Street Address2';
                $headers[] = 'Patient Status: Yes';
                $headers[] = 'Patient Status: No';
                $headers[] = 'Patient Status: No Access';
                $headers[] = 'Patient Status: Unknown';
                $headers[] = 'Middle Initial';
                $headers[] = 'Core Participant Date';
            }
            $headers[] = 'Participant Origination';
            if ($hasFullDataAccess) {
                $headers[] = 'Deactivation Status';
                $headers[] = 'Deactivation Date';
                $headers[] = 'gRoR Consent Status';
                $headers[] = 'gRoR Consent Date';
                $headers[] = 'COPE May PPI Survey Complete';
                $headers[] = 'COPE May PPI Survey Completion Date';
                $headers[] = 'COPE June PPI Survey Complete';
                $headers[] = 'COPE June PPI Survey Completion Date';
                $headers[] = 'COPE July PPI Survey Complete';
                $headers[] = 'COPE July PPI Survey Completion Date';
                $headers[] = 'Consent Cohort';
            }
            fputcsv($output, $headers);

            for ($i = 0; $i < ceil($limit / $pageSize); $i++) {
                $participants = $this->participantSummarySearch($organization, $params, $app);
                foreach ($participants as $participant) {
                    if ($hasFullDataAccess) {
                        $row = [
                            $participant->id,
                            $participant->biobankId,
                            $participant->lastName,
                            $participant->firstName,
                            WorkQueue::csvDateFromObject($participant->dob),
                            $participant->language,
                            $participant->enrollmentStatus,
                            WorkQueue::csvStatusFromSubmitted($participant->consentForStudyEnrollment),
                            WorkQueue::dateFromString($participant->consentForStudyEnrollmentAuthored, $app->getUserTimezone()),
                            WorkQueue::csvStatusFromSubmitted($participant->consentForElectronicHealthRecords),
                            WorkQueue::dateFromString($participant->consentForElectronicHealthRecordsAuthored, $app->getUserTimezone()),
                            WorkQueue::csvStatusFromSubmitted($participant->consentForCABoR),
                            WorkQueue::dateFromString($participant->consentForCABoRAuthored, $app->getUserTimezone()),
                            $participant->withdrawalStatus == 'NO_USE' ? '1' : '0',
                            WorkQueue::dateFromString($participant->withdrawalAuthored, $app->getUserTimezone()),
                            $participant->streetAddress,
                            $participant->city,
                            $participant->state,
                            $participant->zipCode,
                            $participant->email,
                            $participant->phoneNumber,
                            $participant->sex,
                            $participant->genderIdentity,
                            $participant->race,
                            $participant->education,
                            $participant->numCompletedBaselinePPIModules == 3 ? '1' : '0',
                            $participant->numCompletedPPIModules
                        ];
                        foreach (WorkQueue::$surveys as $survey => $label) {
                            if (in_array($survey, WorkQueue::$initialSurveys)) {
                                $row[] = WorkQueue::csvStatusFromSubmitted($participant->{"questionnaireOn{$survey}"});
                                $row[] = WorkQueue::dateFromString($participant->{"questionnaireOn{$survey}Authored"}, $app->getUserTimezone());
                            }
                        }
                    } else {
                        $row = [
                            $participant->id,
                            $participant->biobankId,
                            $participant->zipCode,
                        ];
                    }
                    $row[] = $participant->physicalMeasurementsStatus == 'COMPLETED' ? '1' : '0';
                    $row[] = WorkQueue::dateFromString($participant->physicalMeasurementsFinalizedTime, $app->getUserTimezone(), false);
                    $row[] = $participant->siteSuffix;
                    $row[] = $participant->organization;
                    $row[] = $participant->evaluationFinalizedSite;
                    $row[] = $participant->samplesToIsolateDNA == 'RECEIVED' ? '1' : '0';
                    $row[] = $participant->numBaselineSamplesArrived;
                    foreach (array_keys(WorkQueue::$samples) as $sample) {
                        $newSample = $sample;
                        foreach (WorkQueue::$samplesAlias as $sampleAlias) {
                            if (array_key_exists($sample, $sampleAlias) && $participant->{"sampleStatus" . $sampleAlias[$sample]} == 'RECEIVED') {
                                $newSample = $sampleAlias[$sample];
                                break;
                            }
                        }
                        $row[] = $participant->{"sampleStatus{$newSample}"} == 'RECEIVED' ? '1' : '0';
                        $row[] = WorkQueue::dateFromString($participant->{"sampleStatus{$newSample}Time"}, $app->getUserTimezone(), false);
                    }
                    $row[] = $participant->orderCreatedSite;
                    $row[] = $participant->withdrawalReason;
                    $row[] = $participant->primaryLanguage;
                    $row[] = WorkQueue::csvStatusFromSubmitted($participant->consentForDvElectronicHealthRecordsSharing);
                    $row[] = WorkQueue::dateFromString($participant->consentForDvElectronicHealthRecordsSharingAuthored, $app->getUserTimezone());
                    if ($hasFullDataAccess) {
                        $row[] = $participant->loginPhoneNumber;
                        $row[] = !empty($participant->streetAddress2) ? $participant->streetAddress2 : '';
                        $workQueue = new WorkQueue;
                        $row[] = $workQueue->getPatientStatus($participant, 'YES', 'export');
                        $row[] = $workQueue->getPatientStatus($participant, 'NO', 'export');
                        $row[] = $workQueue->getPatientStatus($participant, 'NO ACCESS', 'export');
                        $row[] = $workQueue->getPatientStatus($participant, 'UNKNOWN', 'export');
                        $row[] = $participant->middleName;
                        $row[] = WorkQueue::dateFromString($participant->enrollmentStatusCoreStoredSampleTime, $app->getUserTimeZone());
                    }
                    $row[] = $participant->participantOrigin;
                    if ($hasFullDataAccess) {
                        $row[] = $participant->isSuspended ? '1' : '0';
                        $row[] = WorkQueue::dateFromString($participant->suspensionTime, $app->getUserTimezone());
                        $row[] = WorkQueue::csvStatusFromSubmitted($participant->consentForGenomicsROR);
                        $row[] = WorkQueue::dateFromString($participant->consentForGenomicsRORAuthored, $app->getUserTimezone());
                        $row[] = WorkQueue::csvStatusFromSubmitted($participant->{"questionnaireOnCopeMay"});
                        $row[] = WorkQueue::dateFromString($participant->{"questionnaireOnCopeMayAuthored"}, $app->getUserTimezone());
                        $row[] = WorkQueue::csvStatusFromSubmitted($participant->{"questionnaireOnCopeJune"});
                        $row[] = WorkQueue::dateFromString($participant->{"questionnaireOnCopeJuneAuthored"}, $app->getUserTimezone());
                        $row[] = WorkQueue::csvStatusFromSubmitted($participant->{"questionnaireOnCopeJuly"});
                        $row[] = WorkQueue::dateFromString($participant->{"questionnaireOnCopeJulyAuthored"}, $app->getUserTimezone());
                        $row[] = $participant->consentCohort;
                    }
                    fputcsv($output, $row);
                }
                unset($participants);
                if (!$app['pmi.drc.participants']->getNextToken()) {
                    break;
                }
            }
            fwrite($output, "\"\"\n");
            fputcsv($output, ['Confidential Information']);
            fclose($output);
        };
        $filename = "workqueue_{$organization}_" . date('Ymd-His') . '.csv';

        $app->log(Log::WORKQUEUE_EXPORT, [
            'filter' => $params,
            'site' => $site
        ]);

        return $app->stream($stream, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    public function getSiteWorkQueueDownload($app)
    {
        $site = $app['em']->getRepository('sites')->fetchOneBy([
            'deleted' => 0,
            'google_group' => $app->getSiteId()
        ]);
        return !empty($site) ? $site['workqueue_download'] : null;
    }

    public function isDownloadDisabled($value)
    {
        return $value === AdminController::DOWNLOAD_DISABLED;
    }

    public function participantAction($id, Application $app, Request $request)
    {
        $refresh = $request->query->get('refresh');
        $participant = $app['pmi.drc.participants']->getById($id, $refresh);
        if ($refresh) {
            return $app->redirectToRoute('workqueue_participant', [
                'id' => $id
            ]);
        }
        if (!$participant) {
            $app->abort(404);
        }

        if (!$app->hasRole('ROLE_AWARDEE_SCRIPPS') || !in_array($participant->awardee, $app->getAwardeeOrganization())) {
            $app->abort(403);
        }

        $evaluations = $app['em']->getRepository('evaluations')->getEvaluationsWithHistory($id);

        // Internal Orders
        $orders = $app['em']->getRepository('orders')->getParticipantOrdersWithHistory($id);

        // Quanum Orders
        $quanumOrders = $app['pmi.drc.participants']->getOrdersByParticipant($participant->id);
        foreach ($quanumOrders as $quanumOrder) {
            if (in_array($quanumOrder->origin, ['careevolution'])) {
                $orders[] = (new Order($app))->loadFromJsonObject($quanumOrder)->toArray();
            }
        }

        $problems = $app['em']->getRepository('problems')->getParticipantProblemsWithCommentsCount($id);

        return $app['twig']->render('workqueue/participant.html.twig',[
            'participant' => $participant,
            'cacheEnabled' => $app['pmi.drc.participants']->getCacheEnabled(),
            'orders' => $orders,
            'evaluations' => $evaluations,
            'problems' => $problems,
            'displayPatientStatusBlock' => false,
            'readOnly' => true,
            'biobankView' => true
        ]);
    }

}
