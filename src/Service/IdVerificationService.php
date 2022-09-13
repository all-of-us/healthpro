<?php

namespace App\Service;

use App\Audit\Log;
use App\Entity\IdVerification;
use Doctrine\ORM\EntityManagerInterface;

class IdVerificationService
{
    protected $rdrApiService;
    protected $siteService;
    protected $userService;
    protected $loggerService;
    protected $em;

    public function __construct(
        RdrApiService $rdrApiService,
        SiteService $siteService,
        UserService $userService,
        LoggerService $loggerService,
        EntityManagerInterface $em
    ) {
        $this->rdrApiService = $rdrApiService;
        $this->siteService = $siteService;
        $this->userService = $userService;
        $this->loggerService = $loggerService;
        $this->em = $em;
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
        if ($this->sendToRdr($postData)) {
            $verificationData['verifiedTime'] = $now;
            $verificationData['user'] = $this->userService->getUserEntity();
            $verificationData['site'] = $this->siteService->getSiteId();
            $this->saveIdVerification($verificationData);
            return true;
        }
        return false;
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

    public function saveIdVerification($data): void
    {
        $idVerification = new IdVerification();
        $idVerification->setParticipantId($data['participantId']);
        $idVerification->setUser($data['user']);
        $idVerification->setSite($data['site']);
        $idVerification->setVisitType($data['visitType']);
        $idVerification->setVerificationType($data['verificationType']);
        $idVerification->setVerifiedDate($data['verifiedTime']);
        if (isset($data['import'])) {
            $idVerification->setImport($data['import']);
        }
        $idVerification->setCreatedTs(new \DateTime());
        $this->em->persist($idVerification);
        $this->em->flush();
    }
}
