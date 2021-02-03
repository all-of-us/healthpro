<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Helper\WorkQueue;
use Pmi\Drc\CodeBook;


class WorkQueueService
{
    protected $participantSummaryService;
    protected $params;
    protected $em;
    protected $env;
    protected $siteService;
    protected $loggerService;
    protected $order;
    protected $participant;

    public function __construct(
        ParticipantSummaryService $participantSummaryService,
        ParameterBagInterface $params,
        EntityManagerInterface $em,
        UserService $userService,
        SiteService $siteService,
        LoggerService $loggerService
    ) {
        $this->participantSummaryService = $participantSummaryService;
        $this->params = $params;
        $this->em = $em;
        $this->userService = $userService;
        $this->siteService = $siteService;
        $this->loggerService = $loggerService;
    }

    public function participantSummarySearch($organization, &$params, $app, $type = null)
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
        if ($organization === 'salivary_pilot') {
            $rdrParams['hpoId'] = 'UNSET';
            $rdrParams['sampleStatus1SAL2'] = 'RECEIVED';
        } else {
            $rdrParams['hpoId'] = $organization;
        }

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
                    $rdrParams['deceasedStatus'] = 'UNSET';
                } elseif ($params['activityStatus'] === 'deactivated') {
                    $rdrParams['suspensionStatus'] = 'NO_CONTACT';
                    $rdrParams['deceasedStatus'] = 'UNSET';
                } elseif ($params['activityStatus'] === 'deceased') {
                    $rdrParams['deceasedStatus'] = 'APPROVED';
                } elseif ($params['activityStatus'] === 'deceased_pending') {
                    $rdrParams['deceasedStatus'] = 'PENDING';
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
            if ($params['consentCohort'] === 'COHORT_2_PILOT') {
                $rdrParams['consentCohort'] = 'COHORT_2';
                $rdrParams['cohort2PilotFlag'] = 'COHORT_2_PILOT';
            } else {
                $rdrParams['consentCohort'] = $params['consentCohort'];
            }
        }
        if (!empty($params['ehrConsentExpireStatus'])) {
            if ($params['ehrConsentExpireStatus'] === 'ACTIVE') {
                $rdrParams['consentForElectronicHealthRecords'] = 'SUBMITTED';
                $rdrParams['ehrConsentExpireStatus'] = 'UNSET';
            } else {
                $rdrParams['ehrConsentExpireStatus'] = $params['ehrConsentExpireStatus'];
            }
        }
        if (!empty($params['retentionEligibleStatus'])) {
            $rdrParams['retentionEligibleStatus'] = $params['retentionEligibleStatus'];
        }
        if (!empty($params['retentionType'])) {
            $rdrParams['retentionType'] = $params['retentionType'];
        }
        if (!empty($params['isEhrDataAvailable'])) {
            $rdrParams['isEhrDataAvailable'] = $params['isEhrDataAvailable'] === 'yes' ? 1 : 0;
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
            $summaries = $this->participantSummaryService->listParticipantSummaries($rdrParams);
            foreach ($summaries as $summary) {
                if (isset($summary->resource)) {
                    $results[] = new Participant($summary->resource);
                }
            }
        } catch (\Exception $e) {
            $this->rdrError = true;
        }
        return $results;
    }
}