<?php

namespace App\Service;

use App\Drc\CodeBook;
use App\Entity\Site;
use App\Helper\Participant;
use App\Helper\WorkQueue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class WorkQueueService
{
    protected $participantSummaryService;
    protected $params;
    protected $em;
    protected $env;
    protected $userService;
    protected $siteService;
    protected $loggerService;
    protected $authorizationChecker;
    protected $urlGenerator;
    protected $rdrError = false;
    protected $showConsentPDFs = false;

    public function __construct(
        ParticipantSummaryService $participantSummaryService,
        ParameterBagInterface $params,
        EntityManagerInterface $em,
        UserService $userService,
        SiteService $siteService,
        LoggerService $loggerService,
        AuthorizationCheckerInterface $authorizationChecker,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->participantSummaryService = $participantSummaryService;
        $this->params = $params;
        $this->em = $em;
        $this->userService = $userService;
        $this->siteService = $siteService;
        $this->loggerService = $loggerService;
        $this->authorizationChecker = $authorizationChecker;
        $this->urlGenerator = $urlGenerator;
        $this->showConsentPDFs = (bool) $params->has('feature.participantconsentsworkqueue') && $params->get('feature.participantconsentsworkqueue');
    }

    public function participantSummarySearch($organization, &$params, $type = null, $sortColumns = null, $sites = null)
    {
        $rdrParams = [];
        $next = true;

        if ($type === 'wQTable') {
            $rdrParams['_count'] = isset($params['length']) ? $params['length'] : 10;
            $rdrParams['_offset'] = isset($params['start']) ? $params['start'] : 0;

            // Pass sort params
            if (!empty($params['order'][0])) {
                $sortColumnIndex = $params['order'][0]['column'];
                $sortColumnName = $sortColumns[$sortColumnIndex];
                $sortDir = $params['order'][0]['dir'];
                if ($sortDir === 'asc') {
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
            foreach (array_keys($params) as $key) {
                if (in_array($key, WorkQueue::$withdrawnParticipantFields)) {
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

        // Unset site & organization filter if the value doesn't exist in sites & organizations list
        $sitesList = $organizationsList = [];

        foreach ($sites as $site) {
            $sitesList[] = $site->getGoogleGroup();
            $organizationsList[] = $site->getOrganizationId();
        }

        if ($sitesList && !empty($params['site']) &&
            $params['site'] !== 'UNSET' && !in_array($params['site'], $sitesList)) {
            unset($params['site']);
        }
        if ($organizationsList && !empty($params['organization_id']) &&
            $params['organization_id'] !== 'UNSET' && !in_array($params['organization_id'], $organizationsList)) {
            unset($params['organization_id']);
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
        if (!empty($params['enrollmentStatusV3_2'])) {
            $rdrParams['enrollmentStatusV3_2'] = $params['enrollmentStatusV3_2'];
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
        if (!empty($params['EtMConsent'])) {
            $rdrParams['consentForEtM'] = $params['EtMConsent'];
        }
        if (!empty($params['NphStudyStatus'])) {
            if ($params['NphStudyStatus'] === 'DEACTIVATED') {
                $rdrParams['nphDeactivation'] = 1;
            } elseif ($params['NphStudyStatus'] === 'NOT_CONSENTED') {
                $rdrParams['consentForNphModule1'] = 0;
            } elseif ($params['NphStudyStatus'] === 'MODULE_1_CONSENTED') {
                $rdrParams['consentForNphModule1'] = 1;
            } elseif ($params['NphStudyStatus'] === 'WITHDRAWN') {
                $rdrParams['nphWithdrawal'] = 1;
            }
        }
        foreach (WorkQueue::$rdrPmbFilterParams as $rdrFilterKey) {
            if (!empty($params[$rdrFilterKey])) {
                $rdrParams[$rdrFilterKey] = $params[$rdrFilterKey];
            }
        }
        // Add site prefix
        if (!empty($params['site'])) {
            $site = $params['site'];
            if ($site !== 'UNSET') {
                $site = \App\Security\User::SITE_PREFIX . $site;
            }
            $rdrParams['site'] = $site;
        }
        // Add enrollment site prefix
        if (!empty($params['enrollmentSite'])) {
            $enrollmentSite = $params['enrollmentSite'];
            if ($enrollmentSite !== 'UNSET') {
                $enrollmentSite = \App\Security\User::SITE_PREFIX . $enrollmentSite;
            }
            $rdrParams['enrollmentSite'] = $enrollmentSite;
        }
        if (!empty($params['organization_id'])) {
            $rdrParams['organization'] = $params['organization_id'];
        }
        // Patient status query parameter format Organization:Status
        if (!empty($params['patientStatus']) && !empty($params['siteOrganizationId'])) {
            $rdrParams['patientStatus'] = $params['siteOrganizationId'] . ':' . $params['patientStatus'];
        }
        if (!empty($params['pediatricStatus'])) {
            $pediatricStatus = $params['pediatricStatus'];
            if ($params['pediatricStatus'] === 'SUBMITTED') {
                $pediatricStatus = 1;
            }
            $rdrParams['isPediatric'] = $pediatricStatus;
        }

        // Participant consents tab advanced filters
        if (!empty($params['consentForStudyEnrollment'])) {
            $rdrParams['consentForStudyEnrollment'] = $params['consentForStudyEnrollment'];
        }
        if (!empty($params['questionnaireOnDnaProgram'])) {
            $rdrParams['questionnaireOnDnaProgram'] = $params['questionnaireOnDnaProgram'];
        }
        if (!empty($params['consentForDvElectronicHealthRecordsSharing'])) {
            $rdrParams['consentForDvElectronicHealthRecordsSharing'] = $params['consentForDvElectronicHealthRecordsSharing'];
        }
        if (!empty($params['consentForCABoR'])) {
            $rdrParams['consentForCABoR'] = $params['consentForCABoR'];
        }
        if (!empty($params['primaryLanguage'])) {
            $rdrParams['primaryLanguage'] = $params['primaryLanguage'];
        }

        // Participant consents tab participants lookup
        if (!empty($params['lastName']) && !empty($params['dateOfBirth'])) {
            $rdrParams['lastName'] = $params['lastName'];
            $rdrParams['dateOfBirth'] = $params['dateOfBirth'];
            if (!empty($params['middleName'])) {
                $rdrParams['middleName'] = $params['middleName'];
            }
        }
        if (!empty($params['participantId'])) {
            $rdrParams['participantId'] = substr($params['participantId'], 1);
        }

        if (isset($params['hasCoreData'])) {
            $rdrParams['hasCoreData'] = $params['hasCoreData'];
        }

        if (!empty($params['ageRange']) || WorkQueue::hasDateFields($params)) {
            $rdrParams = http_build_query($rdrParams, '', '&', PHP_QUERY_RFC3986);
        }

        // convert age range to dob filters - using string instead of array to support multiple params with same name
        if (isset($params['ageRange'])) {
            $ageRange = $params['ageRange'];

            $dateOfBirthFilters = CodeBook::ageRangeToDob($ageRange);
            foreach ($dateOfBirthFilters as $filter) {
                $rdrParams .= '&dateOfBirth=' . rawurlencode($filter);
            }
        }

        if (WorkQueue::hasDateFields($params)) {
            $rdrParams .= WorkQueue::getDateFilterParams($params);
        }

        $results = [];
        try {
            $summaries = $this->participantSummaryService->listWorkQueueParticipantSummaries($rdrParams, $next);
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

    public function generateTableRows($participants)
    {
        $e = function ($string) {
            return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $rows = [];
        $userTimezone = $this->userService->getUser()->getTimezone();
        foreach ($participants as $participant) {
            $row = [];
            //Identifiers and status
            foreach (WorkQueue::$columns as $field) {
                $columnDef = WorkQueue::$columnsDef[$field];
                if (isset($columnDef['display_na'])) {
                    $row[$field] = WorkQueue::getPediatricAdultString($columnDef['display_na'], $participant->isPediatric);
                    if ($row[$field] == 'N/A') {
                        continue;
                    }
                }
                if (isset($columnDef['consentMethod'])) {
                    $row[$field] = $this->getConsent($participant, $columnDef);
                } elseif (isset($columnDef['generateLink'])) {
                    $childIcon = isset($columnDef['displayPediatricIcon']) && $participant->isPediatric ? WorkQueue::HTML_CHILD_ICON : '';
                    if ($this->authorizationChecker->isGranted('ROLE_USER') || $this->authorizationChecker->isGranted('ROLE_AWARDEE_SCRIPPS')) {
                        $row[$field] = $childIcon . $this->generateLink($participant->id, $participant->{$columnDef['rdrField']});
                    } else {
                        $row[$field] = $childIcon . $e($participant->{$columnDef['rdrField']});
                    }
                } elseif (isset($columnDef['formatDate'])) {
                    if (!empty($participant->{$columnDef['rdrField']})) {
                        $row[$field] = $participant->{$columnDef['rdrField']}->format('m/d/Y');
                    } else {
                        $row[$field] = '';
                    }
                } elseif (isset($columnDef['serviceMethod'])) {
                    $row[$field] = $this->siteService->{$columnDef['serviceMethod']}($e($participant->{$columnDef['rdrField']}));
                } elseif (isset($columnDef['type'])) {
                    if ($columnDef['type'] === 'sample') {
                        $newSample = $field;
                        foreach (WorkQueue::$samplesAlias as $sampleAlias) {
                            if (array_key_exists($field, $sampleAlias) && $participant->{'sampleStatus' . $sampleAlias[$field]} === 'RECEIVED') {
                                $newSample = $sampleAlias[$field];
                                break;
                            }
                        }
                        $row[$field] = WorkQueue::displayStatus(
                            $participant->{'sampleStatus' . $newSample},
                            'RECEIVED',
                            $userTimezone,
                            $participant->{'sampleStatus' . $newSample . 'Time'},
                            false
                        );
                        if ($field === '1SAL' && $participant->{'sampleStatus' . $newSample} === 'RECEIVED' && $participant->{'sampleStatus' . $newSample . 'Time'} && $participant->sample1SAL2CollectionMethod) {
                            $row[$field] .= ' ' . $e($participant->sample1SAL2CollectionMethod);
                        }
                    } elseif ($columnDef['type'] === 'participantStatus') {
                        $row[$field] = $e($participant->{$columnDef['rdrField']}) . $this->getEnrollmentStatusTime($participant, $userTimezone);
                    } elseif ($columnDef['type'] === 'patientStatus') {
                        $row[$field] = $this->{$columnDef['method']}($participant, $columnDef['value']);
                    } elseif ($columnDef['type'] === 'activityStatus') {
                        $row[$field] = WorkQueue::{$columnDef['method']}($participant, $userTimezone);
                    } elseif ($columnDef['type'] === 'firstEhrConsent') {
                        $row[$field] = WorkQueue::{$columnDef['method']}(
                            $participant->{$columnDef['rdrField']},
                            $userTimezone,
                            'ehr'
                        );
                    } elseif ($columnDef['type'] === 'ppiStatus') {
                        if ($participant->{$columnDef['rdrField']} == 3) {
                            $row[$field] = WorkQueue::HTML_SUCCESS;
                        } else {
                            $row[$field] = WorkQueue::HTML_DANGER;
                        }
                    }
                } elseif (isset($columnDef['wqServiceMethod'])) {
                    $row[$field] = $this->{$columnDef['wqServiceMethod']}($participant->{$columnDef['rdrField']});
                } elseif (isset($columnDef['method'])) {
                    if (isset($columnDef['rdrDateField'])) {
                        if (isset($columnDef['otherField'])) {
                            $row[$field] = WorkQueue::{$columnDef['method']}(
                                $participant->{$columnDef['otherField']},
                                $participant->{$columnDef['rdrField']},
                                $participant->{$columnDef['rdrDateField']},
                                $userTimezone
                            );
                        } elseif (isset($columnDef['statusText'])) {
                            $row[$field] = WorkQueue::{$columnDef['method']}(
                                $participant->{$columnDef['rdrField']},
                                $columnDef['statusText'],
                                $userTimezone,
                                $participant->{$columnDef['rdrDateField']},
                                false
                            );
                        } elseif (isset($columnDef['params'])) {
                            if ($columnDef['params'] === 5) {
                                $row[$field] = WorkQueue::{$columnDef['method']}(
                                    $participant->{$columnDef['rdrField']},
                                    $participant->{$columnDef['rdrDateField']},
                                    $userTimezone,
                                    $columnDef['displayTime'],
                                    (isset($columnDef['pdfPath']) && $participant->{$columnDef['pdfPath']})
                                        ? $this->urlGenerator->generate('participant_consent', [
                                        'id' => $participant->id,
                                        'consentType' => $columnDef['rdrField']
                                    ])
                                        : null
                                );
                            } elseif ($columnDef['params'] === 4) {
                                $row[$field] = WorkQueue::{$columnDef['method']}(
                                    $participant->{$columnDef['rdrField']},
                                    $participant->{$columnDef['rdrDateField']},
                                    $userTimezone,
                                    $columnDef['displayTime']
                                );
                            } elseif ($columnDef['params'] === 3) {
                                $row[$field] = WorkQueue::{$columnDef['method']}(
                                    $participant->{$columnDef['rdrField']},
                                    $participant->{$columnDef['rdrDateField']},
                                    $userTimezone
                                );
                            }
                        }
                    } elseif (isset($columnDef['ancillaryStudy']) && $columnDef['ancillaryStudy']) {
                        $row[$field] = WorkQueue::{$columnDef['method']}(
                            $participant,
                            $userTimezone,
                            $columnDef['displayTime']
                        );
                    } elseif (isset($columnDef['statusText'])) {
                        $row[$field] = WorkQueue::{$columnDef['method']}(
                            $participant->{$columnDef['rdrField']},
                            $columnDef['statusText'],
                            $userTimezone
                        );
                    } elseif (isset($columnDef['names'])) {
                        foreach (array_keys($columnDef['names']) as $type) {
                            $row["{$type}Consent"] = WorkQueue::{$columnDef['method']}($participant->{$columnDef['rdrField']}, $type, $userTimezone);
                        }
                    } elseif (isset($columnDef['userTimezone'])) {
                        $row[$field] = WorkQueue::{$columnDef['method']}(
                            $participant->{$columnDef['rdrField']},
                            $userTimezone
                        );
                    } else {
                        $row[$field] = WorkQueue::{$columnDef['method']}($participant->{$columnDef['rdrField']});
                    }
                } else {
                    $row[$field] = $e($participant->{$columnDef['rdrField']});
                }
            }
            $row['isWithdrawn'] = $participant->isWithdrawn; // Used to add withdrawn class in the data tables
            array_push($rows, $row);
        }
        return $rows;
    }

    public function generateConsentTableRows($participants)
    {
        $e = function ($string) {
            return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $rows = [];
        $userTimezone = $this->userService->getUser()->getTimezone();
        foreach ($participants as $participant) {
            $row = [];
            foreach (WorkQueue::$consentColumns as $field) {
                $columnDef = WorkQueue::$columnsDef[$field];
                if (isset($columnDef['display_na'])) {
                    $row[$field] = WorkQueue::getPediatricAdultString($columnDef['display_na'], $participant->isPediatric);
                    if ($row[$field] !== '') {
                        continue;
                    }
                }
                if (isset($columnDef['consentMethod'])) {
                    $row[$field] = $this->getConsent($participant, $columnDef);
                } elseif (isset($columnDef['generateLink'])) {
                    if ($this->authorizationChecker->isGranted('ROLE_USER') || $this->authorizationChecker->isGranted('ROLE_AWARDEE_SCRIPPS')) {
                        $row[$field] = $this->generateLink($participant->id, $participant->{$columnDef['rdrField']});
                    } else {
                        $row[$field] = $e($participant->{$columnDef['rdrField']});
                    }
                } elseif (isset($columnDef['formatDate'])) {
                    if (!empty($participant->{$columnDef['rdrField']})) {
                        $row[$field] = $participant->{$columnDef['rdrField']}->format('m/d/Y');
                    } else {
                        $row[$field] = '';
                    }
                } elseif (isset($columnDef['method'])) {
                    if (isset($columnDef['rdrDateField'])) {
                        if (isset($columnDef['otherField'])) {
                            $row[$field] = WorkQueue::{$columnDef['method']}(
                                $participant->{$columnDef['otherField']},
                                $participant->{$columnDef['rdrField']},
                                $participant->{$columnDef['rdrDateField']},
                                $userTimezone
                            );
                        } else {
                            $row[$field] = WorkQueue::{$columnDef['method']}(
                                $participant->{$columnDef['rdrField']},
                                $participant->{$columnDef['rdrDateField']},
                                $userTimezone,
                                true,
                                (isset($columnDef['pdfPath']) && $participant->{$columnDef['pdfPath']})
                                    ? $this->urlGenerator->generate('participant_consent', [
                                    'id' => $participant->id,
                                    'consentType' => $columnDef['rdrField']
                                ])
                                    : null
                            );
                        }
                    } elseif (isset($columnDef['names'])) {
                        foreach (array_keys($columnDef['names']) as $type) {
                            $row["{$type}Consent"] = WorkQueue::{$columnDef['method']}($participant->{$columnDef['rdrField']}, $type, $userTimezone);
                        }
                    }
                } else {
                    $row[$field] = $e($participant->{$columnDef['rdrField']});
                }
            }
            $row['isWithdrawn'] = $participant->isWithdrawn; // Used to add withdrawn class in the data tables
            array_push($rows, $row);
        }
        return $rows;
    }

    public function generateExportRow($participant, $workQueueColumns = null)
    {
        $userTimezone = $this->userService->getUser()->getTimezone();
        $row = [];
        if ($workQueueColumns) {
            WorkQueue::mapExportColumns($workQueueColumns);
        }
        foreach (WorkQueue::$exportColumns as $field) {
            if ($workQueueColumns && !in_array($field, $workQueueColumns)) {
                continue;
            }
            $columnDef = WorkQueue::$columnsDef[$field];
            if ($field === 'dateOfDeath') {
                $row[] = $participant->dateOfDeath ? date('n/j/Y', strtotime($participant->dateOfDeath)) : '';
            } elseif (isset($columnDef['type']) && $columnDef['type'] === 'patientStatus') {
                $row[] = $this->{$columnDef['method']}($participant, $columnDef['value'], 'export');
            } elseif (isset($columnDef['type']) && $columnDef['type'] === 'sample') {
                $newSample = $field;
                foreach (WorkQueue::$samplesAlias as $sampleAlias) {
                    if (array_key_exists($field, $sampleAlias) && $participant->{'sampleStatus' . $sampleAlias[$field]} === 'RECEIVED') {
                        $newSample = $sampleAlias[$field];
                        break;
                    }
                }
                $row[] = $participant->{"sampleStatus{$newSample}"} === 'RECEIVED' ? '1' : '0';
                $row[] = WorkQueue::dateFromString($participant->{"sampleStatus{$newSample}Time"}, $userTimezone, false);
            } elseif (isset($columnDef['csvStatusText'])) {
                if (isset($columnDef['csvNames'])) {
                    $row[] = $participant->{$columnDef['rdrField']} === $columnDef['csvStatusText'] ? 1 : 0;
                    $displayTime = isset($columnDef['csvDisplayTime']) ? $columnDef['csvDisplayTime'] : true;
                    $row[] = WorkQueue::dateFromString($participant->{$columnDef['rdrDateField']}, $userTimezone, $displayTime);
                } else {
                    if (isset($columnDef['rdrDateField'])) {
                        $row[] = $participant->{$columnDef['rdrField']} === $columnDef['csvStatusText'] ? WorkQueue::dateFromString(
                            $participant->{$columnDef['rdrDateField']},
                            $userTimezone,
                            false
                        ) : '';
                    } else {
                        $row[] = $participant->{$columnDef['rdrField']} === $columnDef['csvStatusText'] ? '1' : '0';
                    }
                }
            } elseif (isset($columnDef['csvMethod'])) {
                if (isset($columnDef['otherField'])) {
                    $row[] = WorkQueue::{$columnDef['csvMethod']}(
                        $participant->{$columnDef['rdrField']},
                        $participant->{$columnDef['otherField']}
                    );
                    $row[] = WorkQueue::dateFromString($participant->{$columnDef['rdrDateField']}, $userTimezone);
                } elseif (isset($columnDef['ancillaryStudy'])) {
                    foreach (array_keys($columnDef['csvNames']) as $fieldKey) {
                        $row[] = WorkQueue::{$columnDef['csvMethod']}($participant, $fieldKey, $userTimezone);
                    }
                } elseif (isset($columnDef['csvNames'])) {
                    $row[] = WorkQueue::{$columnDef['csvMethod']}($participant->{$columnDef['rdrField']}, $field);
                    $row[] = WorkQueue::{$columnDef['csvMethod']}($participant->{$columnDef['rdrField']}, $field, true, $userTimezone);
                } else {
                    $row[] = WorkQueue::{$columnDef['csvMethod']}($participant->{$columnDef['rdrField']});
                }
            } elseif (isset($columnDef['rdrDateField'])) {
                if (isset($columnDef['csvNames'])) {
                    if (isset($columnDef['fieldCheck'])) {
                        $row[] = $participant->{$columnDef['rdrField']} ? '1' : '0';
                    } else {
                        $row[] = WorkQueue::csvStatusFromSubmitted($participant->{$columnDef['rdrField']});
                    }
                }
                $row[] = WorkQueue::dateFromString($participant->{$columnDef['rdrDateField']}, $userTimezone);
            } elseif (isset($columnDef['fieldCheck'])) {
                $row[] = $participant->{$columnDef['rdrField']} ? '1' : '0';
            } elseif (isset($columnDef['csvFormatDate'])) {
                $row[] = WorkQueue::dateFromString($participant->{$columnDef['rdrField']}, $userTimezone);
            } elseif (isset($columnDef['csvRdrField'])) {
                $row[] = $participant->{$columnDef['csvRdrField']};
            } else {
                $row[] = $participant->{$columnDef['rdrField']};
            }
        }
        return $row;
    }

    public function generateConsentExportRow($participant, $workQueueConsentColumns)
    {
        $userTimezone = $this->userService->getUser()->getTimezone();
        $row = [];
        if ($workQueueConsentColumns) {
            WorkQueue::mapExportColumns($workQueueConsentColumns);
        }
        foreach (WorkQueue::$consentExportColumns as $field) {
            $columnDef = WorkQueue::$columnsDef[$field];
            if (in_array($field, $workQueueConsentColumns)) {
                if (isset($columnDef['csvMethod'])) {
                    if (isset($columnDef['otherField'])) {
                        $row[] = WorkQueue::{$columnDef['csvMethod']}(
                            $participant->{$columnDef['rdrField']},
                            $participant->{$columnDef['otherField']}
                        );
                        $row[] = WorkQueue::dateFromString($participant->{$columnDef['rdrDateField']}, $userTimezone);
                    } elseif (isset($columnDef['csvNames'])) {
                        $row[] = WorkQueue::{$columnDef['csvMethod']}($participant->{$columnDef['rdrField']}, $field);
                        $row[] = WorkQueue::{$columnDef['csvMethod']}($participant->{$columnDef['rdrField']}, $field, true, $userTimezone);
                    } else {
                        $row[] = WorkQueue::{$columnDef['csvMethod']}($participant->{$columnDef['rdrField']});
                    }
                } elseif (isset($columnDef['rdrDateField'])) {
                    $row[] = WorkQueue::csvStatusFromSubmitted($participant->{$columnDef['rdrField']});
                    $row[] = WorkQueue::dateFromString($participant->{$columnDef['rdrDateField']}, $userTimezone);
                } elseif (isset($columnDef['csvFormatDate'])) {
                    $row[] = WorkQueue::dateFromString($participant->{$columnDef['rdrField']}, $userTimezone);
                } else {
                    $row[] = $participant->{$columnDef['rdrField']};
                }
            }
        }
        return $row;
    }

    public function canExport()
    {
        if ($this->authorizationChecker->isGranted('ROLE_AWARDEE')) {
            return true;
        }
        $site = $this->em->getRepository(Site::class)->findOneBy([
            'deleted' => 0,
            'googleGroup' => $this->siteService->getSiteId(),
            'workqueueDownload' => WorkQueue::FULL_DATA_ACCESS
        ]);
        return !empty($site) ? true : null;
    }

    public function getExportConfiguration()
    {
        return [
            'limit' => $this->params->has('workqueue_export_limit') ? $this->params->get('workqueue_export_limit') : WorkQueue::LIMIT_EXPORT,
            'pageSize' => $this->params->has('workqueue_export_page_size') ? $this->params->get('workqueue_export_page_size') : WorkQueue::LIMIT_EXPORT_PAGE_SIZE
        ];
    }

    public function isRdrError()
    {
        return $this->rdrError;
    }

    public function getTotal()
    {
        return $this->participantSummaryService->getTotal();
    }

    public function getNextToken()
    {
        return $this->participantSummaryService->getNextToken();
    }

    private function generateLink($id, $name = null, $type = null)
    {
        if ($this->authorizationChecker->isGranted('ROLE_USER')) {
            $url = $this->urlGenerator->generate('participant', ['id' => $id]);
        } else {
            $url = $this->urlGenerator->generate('workqueue_participant', ['id' => $id]);
        }
        if ($type === 'id') {
            $name = $id;
        }
        $text = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf('<a href="%s">%s</a>', $url, $text);
    }

    private function getPatientStatus($participant, $value, $type = 'wq')
    {
        // Clear patient status for withdrawn participants
        if ($participant->isWithdrawn) {
            return '';
        }
        $organizations = [];
        if (is_array($participant->patientStatus)) {
            foreach ($participant->patientStatus as $patientStatus) {
                if ($patientStatus->status === $value) {
                    if ($type === 'export') {
                        $organizations[] = $patientStatus->organization;
                    } else {
                        $organizations[] = $this->siteService->getOrganizationDisplayName($patientStatus->organization);
                    }
                }
            }
            return implode('; ', $organizations);
        }
        return '';
    }

    private function getEnrollmentStatusTime($participant, $userTimezone)
    {
        if ($participant->rdrData->enrollmentStatusV3_2 === 'PARTICIPANT_PLUS_EHR') {
            $time = $participant->enrollmentStatusParticipantPlusEhrV3_2Time;
        } elseif ($participant->rdrData->enrollmentStatusV3_2 === 'ENROLLED_PARTICIPANT') {
            $time = $participant->enrollmentStatusEnrolledParticipantV3_2Time;
        } elseif ($participant->rdrData->enrollmentStatusV3_2 === 'PARTICIPANT') {
            $time = $participant->enrollmentStatusParticipantV3_2Time;
        } elseif ($participant->rdrData->enrollmentStatusV3_2 === 'PMB_ELIGIBLE') {
            $time = $participant->enrollmentStatusPmbEligibleV3_2Time;
        } elseif ($participant->rdrData->enrollmentStatusV3_2 === 'CORE_PARTICIPANT') {
            $time = $participant->enrollmentStatusCoreV3_2Time;
        } elseif ($participant->rdrData->enrollmentStatusV3_2 === 'CORE_MINUS_PM') {
            $time = $participant->enrollmentStatusCoreMinusPmV3_2Time;
        } else {
            $time = null;
        }
        if (!empty($time)) {
            return '<br>' . WorkQueue::dateFromString($time, $userTimezone);
        }
        return '';
    }

    private function getConsent($participant, $columnDef): string
    {
        if (array_key_exists('statusDisplay', $columnDef)) {
            $statusDisplay = $columnDef['statusDisplay'][$participant->{$columnDef['rdrField']}];
        } else {
            $statusDisplay = null;
        }
        return WorkQueue::{$columnDef['consentMethod']}(
            $participant->id,
            $participant->{$columnDef['reconsentField']},
            $participant->{$columnDef['reconsentPdfPath']} ? $this->urlGenerator->generate('participant_consent', [
                'id' => $participant->id,
                'consentType' => $columnDef['reconsentField']
            ]) : null,
            $participant->{$columnDef['rdrField']},
            $participant->{$columnDef['rdrDateField']},
            $participant->{$columnDef['pdfPath']} ? $this->urlGenerator->generate('participant_consent', [
                'id' => $participant->id,
                'consentType' => $columnDef['rdrField']
            ]) : null,
            $columnDef['historicalType'],
            $this->userService->getUser()->getTimezone(),
            $statusDisplay
        );
    }

    private function getRelatedParticipants(string|array|null $relatedParticipants): string
    {
        if (!is_array($relatedParticipants)) {
            return 'N/A';
        }
        $participantIds = array_map(function ($participant) {
            return $this->generateLink($participant->participantId, null, 'id');
        }, $relatedParticipants);

        return implode('<br>', $participantIds);
    }
}
