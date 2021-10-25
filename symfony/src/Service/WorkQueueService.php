<?php

namespace App\Service;

use App\Entity\Site;
use App\Helper\Participant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Helper\WorkQueue;
use App\Drc\CodeBook;
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

    public function participantSummarySearch($organization, &$params, $type = null, $sortColumns  = null)
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
                $site = \App\Security\User::SITE_PREFIX . $site;
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

        // Participant consents tab advanced filters
        if (!empty($params['consentForStudyEnrollment'])) {
            $rdrParams['consentForStudyEnrollment'] = $params['consentForStudyEnrollment'];
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

        if (!empty($params['ageRange']) || WorkQueue::hasDateFields($params)) {
            $rdrParams = http_build_query($rdrParams, null, '&', PHP_QUERY_RFC3986);
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
            if ($this->authorizationChecker->isGranted('ROLE_USER') || $this->authorizationChecker->isGranted('ROLE_AWARDEE_SCRIPPS')) {
                $row['lastName'] = $this->generateLink($participant->id, $participant->lastName);
                $row['middleName'] = $this->generateLink($participant->id, $participant->middleName);
                $row['firstName'] = $this->generateLink($participant->id, $participant->firstName);
            } else {
                $row['lastName'] = $e($participant->lastName);
                $row['firstName'] = $e($participant->firstName);
                $row['middleName'] = $e($participant->middleName);
            }
            if (!empty($participant->dob)) {
                $row['dateOfBirth'] = $participant->dob->format('m/d/Y');
            } else {
                $row['dateOfBirth'] = '';
            }
            $row['patientStatusYes'] = $this->getPatientStatus($participant, 'YES');
            $row['patientStatusNo'] = $this->getPatientStatus($participant, 'NO');
            $row['patientStatusUnknown'] = $this->getPatientStatus($participant, 'NO_ACCESS');
            $row['patientStatusNoAccess'] = $this->getPatientStatus($participant, 'UNKNOWN');
            $row['participantId'] = $e($participant->id);
            $row['biobankId'] = $e($participant->biobankId);
            $row['participantOrigin'] = $e($participant->participantOrigin);
            $row['participantStatus'] = $e($participant->enrollmentStatus) . $this->getEnrollementStatusTime($participant, $userTimezone);
            $row['activityStatus'] = WorkQueue::getActivityStatus($participant, $userTimezone);
            $row['withdrawalReason'] = $e($participant->withdrawalReason);
            $row['consentCohort'] = $e($participant->consentCohortText);
            $row['primaryConsent'] = WorkQueue::displayConsentStatus(
                $participant->consentForStudyEnrollment,
                $participant->consentForStudyEnrollmentAuthored,
                $userTimezone,
                true,
                ($this->showConsentPDFs && $participant->consentForStudyEnrollmentFilePath)
                    ? $this->urlGenerator->generate('participant_consent', [
                        'id' => $participant->id,
                        'consentType' => 'consentForStudyEnrollment'
                    ])
                    : null
            );
            $row['firstPrimaryConsent'] = WorkQueue::displayFirstConsentStatusTime(
                $participant->consentForStudyEnrollmentFirstYesAuthored,
                $userTimezone
            );
            $row['questionnaireOnDnaProgram'] = WorkQueue::displayProgramUpdate(
                $participant->consentCohort,
                $participant->questionnaireOnDnaProgram,
                $participant->questionnaireOnDnaProgramAuthored,
                $userTimezone
            );
            $row['firstEhrConsent'] = WorkQueue::displayFirstConsentStatusTime(
                $participant->consentForElectronicHealthRecordsFirstYesAuthored,
                $userTimezone,
                'ehr'
            );
            $row['ehrConsent'] = WorkQueue::displayConsentStatus(
                $participant->consentForElectronicHealthRecords,
                $participant->consentForElectronicHealthRecordsAuthored,
                $userTimezone,
                true,
                ($this->showConsentPDFs && $participant->consentForElectronicHealthRecordsFilePath)
                    ? $this->urlGenerator->generate('participant_consent', [
                        'id' => $participant->id,
                        'consentType' => 'consentForElectronicHealthRecords'
                    ])
                    : null
            );
            $row['ehrConsentExpireStatus'] = WorkQueue::displayEhrConsentExpireStatus(
                $participant->consentForElectronicHealthRecords,
                $participant->ehrConsentExpireStatus,
                $participant->ehrConsentExpireAuthored,
                $userTimezone
            );
            $row['gRoRConsent'] = WorkQueue::displayGenomicsConsentStatus(
                $participant->consentForGenomicsROR,
                $participant->consentForGenomicsRORAuthored,
                $userTimezone,
                true,
                ($this->showConsentPDFs && $participant->consentForGenomicsRORFilePath)
                    ? $this->urlGenerator->generate('participant_consent', [
                        'id' => $participant->id,
                        'consentType' => 'consentForGenomicsROR'
                    ])
                    : null
            );
            $row['primaryLanguage'] = $e($participant->primaryLanguage);
            $row['dvEhrStatus'] = WorkQueue::displayConsentStatus(
                $participant->consentForDvElectronicHealthRecordsSharing,
                $participant->consentForDvElectronicHealthRecordsSharingAuthored,
                $userTimezone
            );
            $row['caborConsent'] = WorkQueue::displayConsentStatus(
                $participant->consentForCABoR,
                $participant->consentForCABoRAuthored,
                $userTimezone,
                true,
                ($this->showConsentPDFs && $participant->consentForCABoRFilePath)
                    ? $this->urlGenerator->generate('participant_consent', [
                        'id' => $participant->id,
                        'consentType' => 'consentForCABoR'
                    ])
                    : null
            );
            foreach (array_keys(WorkQueue::$digitalHealthSharingTypes) as $type) {
                $row["{$type}Consent"] = WorkQueue::getDigitalHealthSharingStatus($participant->digitalHealthSharingStatus, $type, $userTimezone);
            }
            $row['retentionEligibleStatus'] = WorkQueue::getRetentionEligibleStatus(
                $participant->retentionEligibleStatus,
                $participant->retentionEligibleTime,
                $userTimezone
            );
            $row['retentionType'] = WorkQueue::getRetentionType($participant->retentionType);
            $row['isWithdrawn'] = $participant->isWithdrawn; // Used to add withdrawn class in the data tables
            $row['isEhrDataAvailable'] = WorkQueue::getEhrAvailableStatus($participant->isEhrDataAvailable);
            $row['latestEhrReceiptTime'] = WorkQueue::dateFromString($participant->latestEhrReceiptTime, $userTimezone);

            //Contact
            $row['contactMethod'] = $e($participant->recontactMethod);
            if ($participant->getAddress()) {
                $row['address'] = $e($participant->getAddress());
            } else {
                $row['address'] = '';
            }
            $row['email'] = $e($participant->email);
            $row['loginPhone'] = $e($participant->loginPhoneNumber);
            $row['phone'] = $e($participant->phoneNumber);

            //PPI Surveys
            if ($participant->numCompletedBaselinePPIModules == 3) {
                $row['ppiStatus'] = WorkQueue::HTML_SUCCESS;
            } else {
                $row['ppiStatus'] = WorkQueue::HTML_DANGER;
            }
            $row['ppiSurveys'] = $e($participant->numCompletedPPIModules);
            foreach (array_keys(WorkQueue::$surveys) as $field) {
                $row["ppi{$field}"] = WorkQueue::displaySurveyStatus(
                    $participant->{'questionnaireOn' . $field},
                    $participant->{'questionnaireOn' . $field . 'Authored'},
                    $userTimezone
                );
            }

            //In-Person Enrollment
            $row['pairedSite'] = $this->siteService->getSiteDisplayName($e($participant->siteSuffix));
            $row['pairedOrganization'] = $this->siteService->getOrganizationDisplayName($e($participant->organization));
            $row['physicalMeasurementsStatus'] = WorkQueue::displayStatus(
                $participant->physicalMeasurementsStatus,
                'COMPLETED',
                $userTimezone,
                $participant->physicalMeasurementsFinalizedTime,
                false
            );
            $row['evaluationFinalizedSite'] = $this->siteService->getSiteDisplayName($e($participant->evaluationFinalizedSite));
            $row['biobankDnaStatus'] = WorkQueue::displayStatus($participant->samplesToIsolateDNA, 'RECEIVED', $userTimezone);
            if ($participant->numBaselineSamplesArrived >= 7) {
                $row['biobankSamples'] = WorkQueue::HTML_SUCCESS . ' ' . $e($participant->numBaselineSamplesArrived);
            } else {
                $row['biobankSamples'] = $e($participant->numBaselineSamplesArrived);
                ;
            }
            $row['orderCreatedSite'] = $this->siteService->getSiteDisplayName($e($participant->orderCreatedSite));
            foreach (array_keys(WorkQueue::$samples) as $sample) {
                $newSample = $sample;
                foreach (WorkQueue::$samplesAlias as $sampleAlias) {
                    if (array_key_exists($sample, $sampleAlias) && $participant->{"sampleStatus" . $sampleAlias[$sample]} === 'RECEIVED') {
                        $newSample = $sampleAlias[$sample];
                        break;
                    }
                }
                $row["sample{$sample}"] = WorkQueue::displayStatus(
                    $participant->{'sampleStatus' . $newSample},
                    'RECEIVED',
                    $userTimezone,
                    $participant->{'sampleStatus' . $newSample . 'Time'},
                    false
                );
                if ($sample === '1SAL' && $participant->{'sampleStatus' . $newSample} === 'RECEIVED' && $participant->{'sampleStatus' . $newSample . 'Time'} && $participant->sample1SAL2CollectionMethod) {
                    $row["sample{$sample}"] .= ' ' . $e($participant->sample1SAL2CollectionMethod);
                }
            }

            //Demographics
            $row['age'] = $e($participant->age);
            $row['sex'] = $e($participant->sex);
            $row['genderIdentity'] = $e($participant->genderIdentity);
            $row['race'] = $e($participant->race);
            $row['education'] = $e($participant->education);
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
            foreach (WorkQueue::$columnsDef as $field => $columnDef) {
                if (isset($columnDef['generateLink'])) {
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
                    } elseif (isset($columnDef['displayNames'])) {
                        foreach (array_keys($columnDef['displayNames']) as $type) {
                            $row["{$type}Consent"] = WorkQueue::{$columnDef['method']}($participant->{$columnDef['rdrField']}, $type, $userTimezone);
                        }
                    }
                } else {
                    $row[$field] = $e($participant->{$columnDef['rdrField']});
                }
            }
            array_push($rows, $row);
        }
        return $rows;
    }

    public function generateExportRow($participant, $workQueueColumns = null)
    {
        $userTimezone = $this->userService->getUser()->getTimezone();
        $row = [
            $participant->lastName,
            $participant->firstName,
            $participant->middleName,
            WorkQueue::csvDateFromObject($participant->dob),
            $participant->id,
            $participant->biobankId,
            $participant->enrollmentStatus,
            WorkQueue::dateFromString($participant->enrollmentStatusCoreStoredSampleTime, $userTimezone),
            $participant->isWithdrawn ? '1' : '0',
            WorkQueue::dateFromString($participant->withdrawalAuthored, $userTimezone),
            $participant->withdrawalReason,
            $participant->isSuspended ? '1' : '0',
            WorkQueue::dateFromString($participant->suspensionTime, $userTimezone),
            WorkQueue::csvDeceasedStatus($participant->deceasedStatus),
            $participant->dateOfDeath ? date('n/j/Y', strtotime($participant->dateOfDeath)) : '',
            $participant->deceasedStatus === 'APPROVED' ? WorkQueue::dateFromString($participant->deceasedAuthored, $userTimezone, false) : '',
            $participant->participantOrigin,
            $participant->consentCohortText,
            WorkQueue::dateFromString($participant->consentForStudyEnrollmentFirstYesAuthored, $userTimezone),
            WorkQueue::csvStatusFromSubmitted($participant->consentForStudyEnrollment),
            WorkQueue::dateFromString($participant->consentForStudyEnrollmentAuthored, $userTimezone),
            WorkQueue::csvStatusFromSubmitted($participant->questionnaireOnDnaProgram),
            WorkQueue::dateFromString($participant->questionnaireOnDnaProgramAuthored, $userTimezone),
            WorkQueue::dateFromString($participant->consentForElectronicHealthRecordsFirstYesAuthored, $userTimezone),
            WorkQueue::csvStatusFromSubmitted($participant->consentForElectronicHealthRecords),
            WorkQueue::dateFromString($participant->consentForElectronicHealthRecordsAuthored, $userTimezone),
            WorkQueue::csvEhrConsentExpireStatus($participant->ehrConsentExpireStatus, $participant->consentForElectronicHealthRecords),
            WorkQueue::dateFromString($participant->ehrConsentExpireAuthored, $userTimezone),
            WorkQueue::csvStatusFromSubmitted($participant->consentForGenomicsROR),
            WorkQueue::dateFromString($participant->consentForGenomicsRORAuthored, $userTimezone),
            $participant->primaryLanguage,
            WorkQueue::csvStatusFromSubmitted($participant->consentForDvElectronicHealthRecordsSharing),
            WorkQueue::dateFromString($participant->consentForDvElectronicHealthRecordsSharingAuthored, $userTimezone),
            WorkQueue::csvStatusFromSubmitted($participant->consentForCABoR),
            WorkQueue::dateFromString($participant->consentForCABoRAuthored, $userTimezone),
            $participant->retentionEligibleStatus === 'ELIGIBLE' ? 1 : 0,
            WorkQueue::dateFromString($participant->retentionEligibleTime, $userTimezone),
            WorkQueue::csvRetentionType($participant->retentionType),
            $participant->isEhrDataAvailable ? 1 : 0,
            WorkQueue::dateFromString($participant->latestEhrReceiptTime, $userTimezone),
            $this->getPatientStatus($participant, 'YES', 'export'),
            $this->getPatientStatus($participant, 'NO', 'export'),
            $this->getPatientStatus($participant, 'NO ACCESS', 'export'),
            $this->getPatientStatus($participant, 'UNKNOWN', 'export'),
            $participant->streetAddress,
            !empty($participant->streetAddress2) ? $participant->streetAddress2 : '',
            $participant->city,
            $participant->state,
            $participant->zipCode,
            $participant->email,
            $participant->loginPhoneNumber,
            $participant->phoneNumber,
            $participant->numCompletedBaselinePPIModules == 3 ? '1' : '0',
            $participant->numCompletedPPIModules,
        ];
        foreach (array_keys(WorkQueue::$surveys) as $survey) {
            if (in_array($survey, WorkQueue::$initialSurveys)) {
                $row[] = WorkQueue::csvStatusFromSubmitted($participant->{"questionnaireOn{$survey}"});
                $row[] = WorkQueue::dateFromString($participant->{"questionnaireOn{$survey}Authored"}, $userTimezone);
            }
        }
        $row[] = $participant->siteSuffix;
        $row[] = $participant->organization;
        $row[] = $participant->physicalMeasurementsStatus === 'COMPLETED' ? '1' : '0';
        $row[] = WorkQueue::dateFromString($participant->physicalMeasurementsFinalizedTime, $userTimezone, false);
        $row[] = $participant->evaluationFinalizedSite;
        $row[] = $participant->samplesToIsolateDNA === 'RECEIVED' ? '1' : '0';
        $row[] = $participant->numBaselineSamplesArrived;
        $row[] = $participant->orderCreatedSite;
        foreach (array_keys(WorkQueue::$samples) as $sample) {
            $newSample = $sample;
            foreach (WorkQueue::$samplesAlias as $sampleAlias) {
                if (array_key_exists($sample, $sampleAlias) && $participant->{"sampleStatus" . $sampleAlias[$sample]} === 'RECEIVED') {
                    $newSample = $sampleAlias[$sample];
                    break;
                }
            }
            $row[] = $participant->{"sampleStatus{$newSample}"} === 'RECEIVED' ? '1' : '0';
            $row[] = WorkQueue::dateFromString($participant->{"sampleStatus{$newSample}Time"}, $userTimezone, false);
        }
        $row[] = $participant->sample1SAL2CollectionMethod;
        $row[] = $participant->sex;
        $row[] = $participant->genderIdentity;
        $row[] = $participant->race;
        $row[] = $participant->education;
        $row[] = WorkQueue::csvStatusFromSubmitted($participant->questionnaireOnCopeFeb);
        $row[] = WorkQueue::dateFromString($participant->questionnaireOnCopeFebAuthored, $userTimezone);
        $row[] = WorkQueue::dateFromString($participant->enrollmentStatusCoreMinusPMTime, $userTimezone);
        $row[] = WorkQueue::csvStatusFromSubmitted($participant->questionnaireOnCopeVaccineMinute1);
        $row[] = WorkQueue::dateFromString($participant->questionnaireOnCopeVaccineMinute1Authored, $userTimezone);
        $row[] = WorkQueue::csvStatusFromSubmitted($participant->questionnaireOnCopeVaccineMinute2);
        $row[] = WorkQueue::dateFromString($participant->questionnaireOnCopeVaccineMinute2Authored, $userTimezone);
        foreach (array_keys(WorkQueue::$digitalHealthSharingTypes) as $type) {
            $row[] = WorkQueue::csvDigitalHealthSharingStatus($participant->digitalHealthSharingStatus, $type);
            $row[] = WorkQueue::csvDigitalHealthSharingStatus($participant->digitalHealthSharingStatus, $type, true, $userTimezone);
        }
        $row[] = WorkQueue::csvStatusFromSubmitted($participant->questionnaireOnSocialDeterminantsOfHealth);
        $row[] = WorkQueue::dateFromString($participant->questionnaireOnSocialDeterminantsOfHealthAuthored, $userTimezone);
        $row[] = WorkQueue::csvStatusFromSubmitted($participant->questionnaireOnCopeVaccineMinute3);
        $row[] = WorkQueue::dateFromString($participant->questionnaireOnCopeVaccineMinute3Authored, $userTimezone);
        return $row;
    }

    public function generateConsentExportRow($participant, $workQueueConsentColumns)
    {
        $userTimezone = $this->userService->getUser()->getTimezone();
        $row = [];
        foreach (WorkQueue::$columnsDef as $field => $columnDef) {
            if (!$columnDef['toggleColumn'] || (in_array("column{$field}", $workQueueConsentColumns))) {
                if (isset($columnDef['csvMethod'])) {
                    if (isset($columnDef['otherField'])) {
                        $row[] = WorkQueue::{$columnDef['csvMethod']}(
                            $participant->{$columnDef['rdrField']},
                            $participant->{$columnDef['otherField']}
                        );
                        $row[] = WorkQueue::dateFromString($participant->{$columnDef['rdrDateField']}, $userTimezone);
                    } elseif (isset($columnDef['displayNames'])) {
                        foreach (array_keys($columnDef['displayNames']) as $type) {
                            $row[] = WorkQueue::{$columnDef['csvMethod']}($participant->{$columnDef['rdrField']}, $type);
                            $row[] = WorkQueue::{$columnDef['csvMethod']}($participant->{$columnDef['rdrField']}, $type, true, $userTimezone);
                        }
                    } else {
                        $row[] = WorkQueue::{$columnDef['csvMethod']}($participant->{$columnDef['rdrField']});
                    }
                } elseif (isset($columnDef['rdrDateField'])) {
                    $row[] = WorkQueue::csvStatusFromSubmitted($participant->{$columnDef['rdrField']});
                    $row[] = WorkQueue::dateFromString($participant->{$columnDef['rdrDateField']}, $userTimezone);
                } else {
                    $row[] = $participant->{$columnDef['rdrField']};
                }
            }
        }
        return $row;
    }

    public function generateLink($id, $name)
    {
        if ($this->authorizationChecker->isGranted('ROLE_USER')) {
            $url = $this->urlGenerator->generate('participant', ['id' => $id]);
        } else {
            $url = $this->urlGenerator->generate('workqueue_participant', ['id' => $id]);
        }
        $text = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf('<a href="%s">%s</a>', $url, $text);
    }

    public function getPatientStatus($participant, $value, $type = 'wq')
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

    public function getEnrollementStatusTime($participant, $userTimezone)
    {
        if ($participant->isCoreParticipant) {
            $time = $participant->enrollmentStatusCoreStoredSampleTime;
        } elseif ($participant->isCoreMinusPMParticipant) {
            $time = $participant->enrollmentStatusCoreMinusPMTime;
        }
        if (!empty($time)) {
            return '<br>' . WorkQueue::dateFromString($time, $userTimezone);
        }
        return '';
    }
}
