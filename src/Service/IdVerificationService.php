<?php

namespace App\Service;

use App\Audit\Log;
use App\Entity\IdVerification;
use App\Entity\IdVerificationRdr;
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
        $obj->verifiedTime = $verificationData['verifiedDate'];
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
        $verificationData['verifiedDate'] = $now->format('Y-m-d\TH:i:s\Z');
        $verificationData['siteGoogleGroup'] = $this->siteService->getSiteIdWithPrefix();
        $postData = $this->getRdrObject($verificationData);
        if ($this->sendToRdr($postData)) {
            $verificationData['verifiedDate'] = $now;
            $verificationData['user'] = $this->userService->getUserEntity();
            $verificationData['site'] = $this->siteService->getSiteId();
            $verificationData['guardianVerified'] = $verificationFormData['guardian_verified'][0] ?? false;
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
            $response = $this->rdrApiService->post('rdr/v1/Onsite/Id/Verification', $postData);
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

    public function saveIdVerification($data): ?int
    {
        try {
            $idVerification = new IdVerification();
            $idVerification->setParticipantId($data['participantId']);
            $idVerification->setUser($data['user']);
            $idVerification->setSite($data['site']);
            $idVerification->setVisitType($data['visitType']);
            $idVerification->setVerificationType($data['verificationType']);
            $idVerification->setVerifiedDate($data['verifiedDate']);
            $idVerification->setGuardianVerified($data['guardianVerified'] ?? false);
            if (isset($data['import'])) {
                $idVerification->setImport($data['import']);
            }
            $createdTs = new \DateTime();
            if (isset($data['createdTs'])) {
                $createdTs = $data['createdTs'];
            }
            $idVerification->setCreatedTs($createdTs);
            $this->em->persist($idVerification);
            $this->em->flush();
            $this->loggerService->log(Log::ID_VERIFICATION_ADD, $idVerification->getId());
            return $idVerification->getId();
        } catch (\Exception $e) {
            $this->loggerService->log('error', $e->getMessage());
            return null;
        }
    }

    //TODO: Remove this once the backfill is done
    public function backfillIdVerificationsRdr(): void
    {
        $idVerificationsRdr = $this->em->getRepository(IdVerificationRdr::class)->getIdVerificationsRdr(50);
        foreach ($idVerificationsRdr as $idVerificationRdr) {
            $idVerificationData = [];
            $idVerificationData['participantId'] = $idVerificationRdr->getParticipantId();
            $user = $this->userService->getUserEntityFromEmail($idVerificationRdr->getEmail());
            $idVerificationData['user'] = $user;
            $idVerificationData['site'] = $idVerificationRdr->getSiteId();
            $idVerificationData['visitType'] = $idVerificationRdr->getVisitType();
            $idVerificationData['verificationType'] = $idVerificationRdr->getVerificationType();
            $idVerificationData['verifiedDate'] = $idVerificationRdr->getVerifiedDate();
            $idVerificationData['createdTs'] = $idVerificationRdr->getCreatedTs();
            if ($insertId = $this->saveIdVerification($idVerificationData)) {
                $idVerificationRdr->setInsertId($insertId);
                $this->em->persist($idVerificationRdr);
            }
        }
        $this->em->flush();
        $this->em->clear();
    }
}
