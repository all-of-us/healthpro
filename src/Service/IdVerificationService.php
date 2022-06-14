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

    public function getRdrObject($participantId, $verificationData): \stdClass
    {
        $obj = new \StdClass();
        $email = $this->userService->getUser()->getEmail();
        $now = new \DateTime();
        $obj->participantId = $participantId;
        $obj->userEmail = $email;
        $obj->verifiedTime = $now->format('Y-m-d\TH:i:s\Z');
        $obj->siteGoogleGroup = $this->siteService->getSiteIdWithPrefix();
        $obj->verificationType = $verificationData['verification_type'];
        $obj->visitType = $verificationData['visit_type'];
        return $obj;
    }

    public function createIdVerification($participantId, $verificationData): bool
    {
        $postData = $this->getRdrObject($participantId, $verificationData);
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

    private function sendToRdr($postData)
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
