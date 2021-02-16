<?php

namespace App\Service;


use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PatientStatusService
{
    protected $rdrApiService;
    protected $siteService;
    protected $userService;
    protected $params;

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

    public function __construct(RdrApiService $rdrApiService, SiteService $siteService, UserService $userService, ParameterBagInterface $params)
    {
        $this->rdrApiService = $rdrApiService;
        $this->siteService = $siteService;
        $this->userService = $userService;
        $this->params = $params;
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
        return
            !$this->siteService->isDVType() &&
            $participant->statusReason !== 'withdrawal' &&
            $participant->statusReason !== 'test-participant' &&
            !$this->siteService->isTestSite() &&
            empty($this->params->get('disable_patient_status_message'));
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

}
