<?php

namespace App\Service;

use App\Entity\PatientStatus;
use App\Entity\PatientStatusHistory;
use App\Entity\PatientStatusImport;
use App\Entity\PatientStatusImportRow;
use App\Helper\Import;
use Doctrine\ORM\EntityManagerInterface;
use App\Audit\Log;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PatientStatusService
{
    protected $rdrApiService;
    protected $siteService;
    protected $userService;
    protected $params;
    protected $em;
    protected $loggerService;
    protected $logger;

    protected $participantId;
    protected $patientStatusId;
    protected $organizationId;
    protected $awardeeId;
    protected $userId;
    protected $userEmail;
    protected $siteId;
    protected $siteWithPrefix;
    protected $comments;
    protected $status;
    protected $createdTs;
    protected $importId;

    public function __construct(
        RdrApiService $rdrApiService,
        SiteService $siteService,
        UserService $userService,
        ParameterBagInterface $params,
        EntityManagerInterface $em,
        LoggerService $loggerService,
        LoggerInterface $logger
    ) {
        $this->rdrApiService = $rdrApiService;
        $this->siteService = $siteService;
        $this->userService = $userService;
        $this->params = $params;
        $this->em = $em;
        $this->loggerService = $loggerService;
        $this->logger = $logger;
    }

    public function getPatientStatus($participantId, $organizationId)
    {
        try {
            $response = $this->rdrApiService->get("rdr/v1/PatientStatus/{$participantId}/Organization/{$organizationId}");
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result)) {
                return $result;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    public function getPatientStatusHistory($participantId, $organizationId)
    {
        try {
            $response = $this->rdrApiService->get("rdr/v1/PatientStatus/{$participantId}/Organization/{$organizationId}/History");
            $result = json_decode($response->getBody()->getContents());
            if (is_array($result)) {
                return $result;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    public function hasAccess($participant)
    {
        $disablePatientStatusMessage = $this->params->has('disable_patient_status_message') ? $this->params->get('disable_patient_status_message') : null;
        return
            !$this->siteService->isDVType() &&
            $participant->statusReason !== 'withdrawal' &&
            $participant->statusReason !== 'test-participant' &&
            !$this->siteService->isTestSite() &&
            empty($disablePatientStatusMessage);
    }

    public function loadData($participantId, $patientStatusId, $formData)
    {
        $this->participantId = $participantId;
        $this->patientStatusId = $patientStatusId;
        $this->organizationId = $this->siteService->getSiteOrganization();
        $this->awardeeId = $this->siteService->getSiteAwardee();
        $this->userId = $this->userService->getUser()->getId();
        $this->userEmail = $this->userService->getUser()->getEmail();
        $this->siteId = $this->siteService->getSiteId();
        $this->siteWithPrefix = $this->siteService->getSiteIdWithPrefix();
        $this->comments = $formData['comments'];
        $this->status = $formData['status'];
        $this->createdTs = new \DateTime();
    }

    public function getRdrObject()
    {
        $obj = new \StdClass();
        $obj->subject = 'Patient/' . $this->participantId;
        $obj->awardee = $this->awardeeId;
        $obj->organization = $this->organizationId;
        $obj->patient_status = $this->status;
        $obj->user = $this->userEmail;
        $obj->site = $this->siteWithPrefix;
        $obj->authored = $this->createdTs->format('Y-m-d\TH:i:s\Z');
        $obj->comment = $this->comments;
        return $obj;
    }

    public function sendToRdr()
    {
        $postData = $this->getRdrObject();
        try {
            $response = $this->rdrApiService->put("rdr/v1/PatientStatus/{$this->participantId}/Organization/$this->organizationId", $postData);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->authored)) {
                return true;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    public function saveData()
    {
        $status = false;
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            //Create patient status if not exists
            if (!empty($this->patientStatusId)) {
                $patientStatus = $this->em->getRepository(PatientStatus::class)->find($this->patientStatusId);
            } else {
                $patientStatus = new PatientStatus();
                $patientStatus->setParticipantId($this->participantId);
                $patientStatus->setOrganization($this->organizationId);
                $patientStatus->setAwardee($this->awardeeId);
                $this->em->persist($patientStatus);
                $this->em->flush();

                $this->loggerService->log(Log::PATIENT_STATUS_ADD, [
                    'id' => $patientStatus->getId()
                ]);
            }

            //Create patient status history
            $patientStatusHistory = new PatientStatusHistory();
            $patientStatusHistory->setUserId($this->userId);
            $patientStatusHistory->setSite($this->siteId);
            $patientStatusHistory->setComments($this->comments);
            $patientStatusHistory->setStatus($this->status);
            $patientStatusHistory->setCreatedTs($this->createdTs);
            $patientStatusHistory->setRdrTs($this->createdTs);
            $patientStatusHistory->setPatientStatus($patientStatus);
            // Set import id if exists
            if (!empty($this->importId)) {
                $patientStatusImport = $this->em->getRepository(PatientStatusImport::class)->find($this->importId);
                $patientStatusHistory->setImport($patientStatusImport);
            }
            $this->em->persist($patientStatusHistory);
            $this->em->flush();

            $this->loggerService->log(Log::PATIENT_STATUS_HISTORY_ADD, [
                'id' => $patientStatusHistory->getId()
            ]);

            //Update history id in patient status table
            $patientStatus->setHistory($patientStatusHistory);
            $this->em->persist($patientStatus);
            $this->em->flush();

            //Log if it's a patient status edit
            if (!empty($this->patientStatusId)) {
                $this->loggerService->log(Log::PATIENT_STATUS_EDIT, [
                    'id' => $this->patientStatusId
                ]);
            }
            $connection->commit();
            $status = true;
        } catch (\Exception $e) {
            $connection->rollback();
        }
        return $status;
    }

    public function deleteUnconfirmedImportData()
    {
        $date = (new \DateTime('UTC'))->modify('-1 hours');
        $date = $date->format('Y-m-d H:i:s');
        $this->em->getRepository(PatientStatusImportRow::class)->deleteUnconfirmedImportData($date);
    }

    public function sendPatientStatusToRdr()
    {
        $limit = $this->params->has('patient_status_queue_limit') ? intval($this->params->get('patient_status_queue_limit')) : 0;
        $patientStatuses = $this->em->getRepository(PatientStatusImportRow::class)->getPatientStatusImportRows($limit);
        $importIds = [];
        foreach ($patientStatuses as $patientStatus) {
            if (!in_array($patientStatus['import_id'], $importIds)) {
                $importIds[] = $patientStatus['import_id'];
            }
            $this->loadDataFromImport($patientStatus);
            $patientStatusImportRow = $this->em->getRepository(PatientStatusImportRow::class)->find($patientStatus['id']);
            if (!empty($patientStatusImportRow)) {
                if ($this->sendToRdr()) {
                    if ($this->saveData()) {
                        $patientStatusImportRow->setRdrStatus(Import::STATUS_SUCCESS);
                        $this->em->persist($patientStatusImportRow);
                        $this->em->flush();
                    }
                } else {
                    $this->logger->error("#{$patientStatus['id']} failed sending to RDR: " . $this->rdrApiService->getLastError());
                    $rdrStatus = Import::STATUS_OTHER_RDR_ERRORS;
                    if ($this->rdrApiService->getLastErrorCode() === 400) {
                        $rdrStatus = Import::STATUS_INVALID_PARTICIPANT_ID;
                    } elseif ($this->rdrApiService->getLastErrorCode() === 500) {
                        $rdrStatus = Import::STATUS_RDR_INTERNAL_SERVER_ERROR;
                    }
                    $patientStatusImportRow->setRdrStatus($rdrStatus);
                    $this->em->persist($patientStatusImportRow);
                    $this->em->flush();
                }
            }
        }
        // Update import status
        $this->updateImportStatus($importIds);
    }

    // Used to send imported patient statuses to rdr
    public function loadDataFromImport($patientStatusHistory)
    {
        $this->participantId = $patientStatusHistory['participant_id'];
        $this->organizationId = $patientStatusHistory['organization'];
        $this->awardeeId = $patientStatusHistory['awardee'];
        $this->userEmail = $patientStatusHistory['user_email'];
        $this->siteWithPrefix = \App\Security\User::SITE_PREFIX . $patientStatusHistory['site'];
        $this->comments = $patientStatusHistory['comments'];
        $this->status = $patientStatusHistory['status'];
        $this->createdTs = new \DateTime($patientStatusHistory['authored']);
        $this->siteId = $patientStatusHistory['site'];
        $this->userId = $patientStatusHistory['user_id'];
        $patientStatusId = $patientStatusHistory['patient_status_id'];
        if (empty($patientStatusId)) {
            $patientStatus = $this->em->getRepository(PatientStatus::class)->findBy([
                'participantId' => $patientStatusHistory['participant_id'],
                'organization' => $patientStatusHistory['organization']
            ]);
            if (!empty($patientStatus)) {
                $patientStatusId = $patientStatus->getId();
            }
        }
        $this->patientStatusId = $patientStatusId;
        $this->importId = $patientStatusHistory['import_id'];
    }

    private function updateImportStatus($importIds)
    {
        foreach ($importIds as $importId) {
            $patientStatusImport = $this->em->getRepository(PatientStatusImport::class)->find($importId);
            if (!empty($patientStatusImport)) {
                $patientStatusImportRows = $this->em->getRepository(PatientStatusImportRow::class)->findBy([
                    'import' => $patientStatusImport,
                    'rdrStatus' => 0
                ]);
                if (empty($patientStatusImportRows)) {
                    $patientStatusImportRows = $this->em->getRepository(PatientStatusImportRow::class)->findBy([
                        'import' => $patientStatusImport,
                        'rdrStatus' => [
                            Import::STATUS_INVALID_PARTICIPANT_ID,
                            Import::STATUS_RDR_INTERNAL_SERVER_ERROR,
                            Import::STATUS_OTHER_RDR_ERRORS
                        ]
                    ]);
                    if (!empty($patientStatusImportRows)) {
                        $patientStatusImport->setImportStatus(Import::COMPLETE_WITH_ERRORS);
                    } else {
                        $patientStatusImport->setImportStatus(Import::COMPLETE);
                    }
                    $this->em->persist($patientStatusImport);
                    $this->em->flush();
                    $this->loggerService->log(Log::PATIENT_STATUS_IMPORT_EDIT, $importId);
                }
            }
        }
    }
}
