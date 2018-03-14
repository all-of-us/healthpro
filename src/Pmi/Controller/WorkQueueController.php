<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Pmi\Audit\Log;
use Pmi\Entities\Participant;
use Pmi\Drc\CodeBook;
use Symfony\Component\HttpFoundation\JsonResponse;
use Pmi\WorkQueue\WorkQueue;

class WorkQueueController extends AbstractController
{
    protected static $name = 'workqueue';
    protected static $routes = [
        ['index', '/', ['method' => 'GET|POST']],
        ['export', '/export.csv']
    ];

    protected $rdrError = false;

    protected $next = true;

    protected function participantSummarySearch($organization, &$params, $app, $type = null)
    {
        $rdrParams = [];
        $tableParams = [];
        if (isset($params['withdrawalStatus']) && $params['withdrawalStatus'] === 'NO_USE') {
            foreach ($params as $key => $value) {
                if ($key === 'withdrawalStatus' || $key === 'organization') {
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
        if (!empty($params['withdrawalStatus'])) {
            $rdrParams['withdrawalStatus'] = $params['withdrawalStatus'];
        }
        if (!empty($params['enrollmentStatus'])) {
            $rdrParams['enrollmentStatus'] = $params['enrollmentStatus'];
        }
        if (!empty($params['consentForElectronicHealthRecords'])) {
            $rdrParams['consentForElectronicHealthRecords'] = $params['consentForElectronicHealthRecords'];
        }
        if (!empty($params['genderIdentity'])) {
            $rdrParams['genderIdentity'] = $params['genderIdentity'];
        }
        if (!empty($params['race'])) {
            $rdrParams['race'] = $params['race'];
        }
        // Add site prefix
        if (!empty($params['site'])) {
            $rdrParams['site'] = \Pmi\Security\User::SITE_PREFIX . $params['site'];
        }

        if ($type == 'wQTable') {
            $rdrParams['_count'] = isset($params['length']) ? $params['length'] : 10;
            // Pass table params
            $tableParams['start'] = isset($params['start']) ? $params['start'] : 0;
            $tableParams['count'] = $rdrParams['_count'];

            // Pass sort params
            if (!empty($params['order'][0])) {
                $sortColumnIndex = $params['order'][0]['column'];
                $sortColumnName = WorkQueue::$wQColumns[$sortColumnIndex];
                $sortDir = $params['order'][0]['dir'];
                if ($sortDir == 'asc') {
                    $rdrParams['_sort'] = $sortColumnName;
                } else {
                    $rdrParams['_sort:desc'] = $sortColumnName;
                }
            }

            // Set next token
            $app['pmi.drc.participants']->setNextToken($app, $tableParams);

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
            $summaries = $app['pmi.drc.participants']->listParticipantSummaries($rdrParams, $this->next);
            if ($type == 'wQTable' && !empty($app['pmi.drc.participants']->getNextToken())) {
                // Set next token in session
                $app['pmi.drc.participants']->setNextToken($app, $tableParams, $type = 'session');
            }
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

        //Add sites filter
        $sites = $app->getSitesFromOrganization($organization);
        if (!empty($sites)) {
            $sitesList = [];
            $sitesList['site']['label'] = 'Paired Site Location';
            foreach ($sites as $site) {
                if (!empty($site['google_group'])) {
                    $sitesList['site']['options'][$site['google_group']] = $site['google_group'];
                }
            }
            $filters = array_merge($filters, $sitesList);
        }

        if ($app->hasRole('ROLE_AWARDEE')) {
            // Add organizations to filters
            $organizationsList = [];
            $organizationsList['organization']['label'] = 'Organization';
            foreach ($organizations as $org) {
                $organizationsList['organization']['options'][$org] = $org;
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
        }
        //For ajax requests
        if ($request->isXmlHttpRequest()) {
            $params = array_merge($params, array_filter($request->request->all()));
            if (empty($params['start'])) {
                $app['session']->set('tokens', []);
            }
            $participants = $this->participantSummarySearch($organization, $params, $app, $type = 'wQTable');
            $ajaxData = [];
            $ajaxData['recordsTotal'] = 10000;
            $ajaxData['recordsFiltered'] = 10000;
            $WorkQueue = new WorkQueue;
            $ajaxData['data'] = $WorkQueue->generateTableRows($participants, $app);
            return new JsonResponse($ajaxData);
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
                'isDownloadDisabled' => $this->isDownloadDisabled($siteWorkQueueDownload)
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

        $hasFullDataAcess = $siteWorkQueueDownload === AdminController::FULL_DATA_ACCESS || $app->hasRole('ROLE_AWARDEE');

        $params = array_filter($request->query->all());
        $params['_count'] = WorkQueue::LIMIT_EXPORT_PAGE_SIZE;
        $params['_sort:desc'] = 'consentForStudyEnrollmentTime';

        $stream = function() use ($app, $params, $organization, $hasFullDataAcess) {
            $output = fopen('php://output', 'w');
            // Add UTF-8 BOM
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['This file contains information that is sensitive and confidential. Do not distribute either the file or its contents.']);
            fwrite($output, "\"\"\n");
            if ($hasFullDataAcess) {
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
                    $headers[] = $label . ' PPI Survey Complete';
                    $headers[] = $label . ' PPI Survey Completion Date';
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
            $headers[] = 'Paired Site Location';
            $headers[] = 'Physical Measurements Location';
            $headers[] = 'Samples for DNA Received';
            $headers[] = 'Biospecimens';
            foreach (WorkQueue::$samples as $sample => $label) {
                $headers[] = $label . ' Collected';
                $headers[] = $label . ' Collection Date';
            }
            $headers[] = 'Biospecimens Location';
            fputcsv($output, $headers);

            for ($i = 0; $i < ceil(WorkQueue::LIMIT_EXPORT / WorkQueue::LIMIT_EXPORT_PAGE_SIZE); $i++) {
                $participants = $this->participantSummarySearch($organization, $params, $app);
                foreach ($participants as $participant) {
                    if ($hasFullDataAcess) {
                        $row = [
                            $participant->id,
                            $participant->biobankId,
                            $participant->lastName,
                            $participant->firstName,
                            WorkQueue::csvDateFromObject($participant->dob),
                            $participant->language,
                            $participant->enrollmentStatus,
                            WorkQueue::csvStatusFromSubmitted($participant->consentForStudyEnrollment),
                            WorkQueue::dateFromString($participant->consentForStudyEnrollmentTime, $app->getUserTimezone()),
                            WorkQueue::csvStatusFromSubmitted($participant->consentForElectronicHealthRecords),
                            WorkQueue::dateFromString($participant->consentForElectronicHealthRecordsTime, $app->getUserTimezone()),
                            WorkQueue::csvStatusFromSubmitted($participant->consentForconsentForCABoR),
                            WorkQueue::dateFromString($participant->consentForCABoRTime, $app->getUserTimezone()),
                            $participant->withdrawalStatus == 'NO_USE' ? '1' : '0',
                            WorkQueue::dateFromString($participant->withdrawalTime, $app->getUserTimezone()),
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
                            $row[] = WorkQueue::csvStatusFromSubmitted($participant->{"questionnaireOn{$survey}"});
                            $row[] = WorkQueue::dateFromString($participant->{"questionnaireOn{$survey}Time"}, $app->getUserTimezone());
                        }
                    } else {
                        $row = [
                            $participant->id,
                            $participant->biobankId,
                            $participant->zipCode,
                        ];                   
                    }
                    $row[] = $participant->physicalMeasurementsStatus == 'COMPLETED' ? '1' : '0';
                    $row[] = WorkQueue::dateFromString($participant->physicalMeasurementsTime, $app->getUserTimezone());
                    $row[] = $participant->siteSuffix;
                    $row[] = $participant->evaluationFinalizedSite;
                    $row[] = $participant->samplesToIsolateDNA == 'RECEIVED' ? '1' : '0';
                    $row[] = $participant->numBaselineSamplesArrived;
                    foreach (WorkQueue::$samples as $sample => $label) {
                        if (array_key_exists($sample, WorkQueue::$samplesAlias[0]) && $participant->{"sampleStatus".WorkQueue::$samplesAlias[0][$sample].""} == 'RECEIVED') {
                            $sample = WorkQueue::$samplesAlias[0][$sample];
                        } elseif (array_key_exists($sample, WorkQueue::$samplesAlias[1]) && $participant->{"sampleStatus".WorkQueue::$samplesAlias[1][$sample].""} == 'RECEIVED') {
                            $sample = WorkQueue::$samplesAlias[1][$sample];
                        }
                        $row[] = $participant->{"sampleStatus{$sample}"} == 'RECEIVED' ? '1' : '0';
                        $row[] = WorkQueue::dateFromString($participant->{"sampleStatus{$sample}Time"}, $app->getUserTimezone());
                    }
                    $row[] = $participant->orderCreatedSite;
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
            'google_group' => $app->getSiteId()
        ]);
        return !empty($site) ? $site['workqueue_download'] : null;
    }

    public function isDownloadDisabled($value)
    {
        return $value === AdminController::DOWNLOAD_DISABLED;
    }
}
