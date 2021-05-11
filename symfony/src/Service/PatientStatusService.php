<?php

namespace App\Service;

use App\Entity\PatientStatus;
use App\Entity\PatientStatusHistory;
use App\Entity\PatientStatusImport;
use App\Entity\PatientStatusImportRow;
use Doctrine\ORM\EntityManagerInterface;
use Pmi\Audit\Log;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PatientStatusService
{
    protected $rdrApiService;
    protected $siteService;
    protected $userService;
    protected $params;
    protected $em;
    protected $loggerService;

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
        LoggerService $loggerService
    ) {
        $this->rdrApiService = $rdrApiService;
        $this->siteService = $siteService;
        $this->userService = $userService;
        $this->params = $params;
        $this->em = $em;
        $this->loggerService = $loggerService;
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
                $patientStatus = new PatientStatus;
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
            $patientStatusHistory = new PatientStatusHistory;
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
}
