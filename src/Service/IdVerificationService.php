<?php

namespace App\Service;

use App\Audit\Log;

class IdVerificationService
{
    protected $rdrApiService;
    protected $siteService;
    protected $userService;
    protected $loggerService;

    public function __construct(
        RdrApiService $rdrApiService,
        SiteService $siteService,
        UserService $userService,
        LoggerService $loggerService
    ) {
        $this->rdrApiService = $rdrApiService;
        $this->siteService = $siteService;
        $this->userService = $userService;
        $this->loggerService = $loggerService;
    }

    public function getRdrObject($verificationData): \stdClass
    {
        $obj = new \StdClass();
        $obj->participantId = $verificationData['participantId'];
        if (isset($verificationData['userEmail'])) {
            $obj->userEmail = $verificationData['userEmail'];
        }
        $obj->verifiedTime = $verificationData['verifiedTime'];
        $obj->siteGoogleGroup = $verificationData['siteGoogleGroup'];
        if (isset($verificationData['verificationType'])) {
            $obj->verificationType = $verificationData['verificationType'];
        }
        if (isset($verificationData['visitType'])) {
            $obj->visitType = $verificationData['visitType'];
        }
        return $obj;
    }

    public function createIdVerification($participantId, $verificationFormData): bool
    {
        $verificationData = [];
        $verificationData['verificationType'] = $verificationFormData['verification_type'];
        $verificationData['visitType'] = $verificationFormData['visit_type'];
        $verificationData['userEmail'] = $this->userService->getUser()->getEmail();
        $verificationData['participantId'] = $participantId;
        $now = new \DateTime();
        $verificationData['verifiedTime'] = $now->format('Y-m-d\TH:i:s\Z');
        $verificationData['siteGoogleGroup'] = $this->siteService->getSiteIdWithPrefix();
        $postData = $this->getRdrObject($verificationData);
        return $this->sendToRdr($postData);
    }

    public function getIdVerifications($participantId): array
    {
        try {
            $response = $this->rdrApiService->get("rdr/v1/Onsite/Id/Verification/{$participantId}");
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && !empty($result->entry)) {
                return $result->entry;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
        }
        return [];
    }

    public function sendToRdr($postData)
    {
        try {
            $response = $this->rdrApiService->post("rdr/v1/Onsite/Id/Verification", $postData);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && !empty($result->verificationType)) {
                $this->loggerService->log(Log::ID_VERIFICATION_ADD, [
                    'participantId' => $postData->participantId
                ]);
                return true;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }
}
