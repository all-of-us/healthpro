<?php

namespace App\Service;

use App\Entity\Incentive;
use App\Entity\IncentiveImport;
use App\Entity\IncentiveImportRow;
use Doctrine\ORM\EntityManagerInterface;
use App\Audit\Log;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class IncentiveService
{
    protected $rdrApiService;
    protected $siteService;
    protected $userService;
    protected $em;
    protected $loggerService;
    protected $params;
    protected $logger;

    public function __construct(
        RdrApiService $rdrApiService,
        SiteService $siteService,
        UserService $userService,
        EntityManagerInterface $em,
        LoggerService $loggerService,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->rdrApiService = $rdrApiService;
        $this->siteService = $siteService;
        $this->userService = $userService;
        $this->em = $em;
        $this->loggerService = $loggerService;
        $this->params = $params;
        $this->logger = $logger;
    }

    public function getRdrObject($incentive, $type = Incentive::CREATE)
    {
        $obj = new \StdClass();
        $email = $this->userService->getUser()->getEmail();
        $now = new \DateTime();
        if ($type !== 'create') {
            $obj->incentiveId = $incentive->getRdrid();
        }
        if ($type === 'cancel') {
            $obj->cancelledBy = $email;
            $obj->cancelledDate = $now;
            $obj->cancel = true;
        } else {
            $obj->createdBy = $email;
            $obj->site = $this->siteService->getSiteIdWithPrefix();
            $obj->dateGiven = $incentive->getIncentiveDateGiven();
            $obj->occurrence = $incentive->getOtherIncentiveOccurrence() ?? $incentive->getIncentiveOccurrence();
            $obj->incentiveType = $incentive->getOtherIncentiveType() ?: $incentive->getIncentiveType();
            if ($incentive->getGiftCardType()) {
                $obj->giftcardType = $incentive->getGiftCardType();
            }
            $obj->amount = $incentive->getIncentiveAmount();
            $obj->notes = $incentive->getNotes();
            $obj->declined = $incentive->getDeclined();
        }
        return $obj;
    }

    public function createIncentive($participantId, $incentiveForm)
    {
        $incentive = $this->getIncentiveFromFormData($incentiveForm);
        $postData = $this->getRdrObject($incentive);
        try {
            $response = $this->rdrApiService->post("rdr/v1/Participant/{$participantId}/Incentives", $postData);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->incentiveId)) {
                $now = new \DateTime();
                $incentive->setParticipantId($participantId);
                $incentive->setCreatedTs($now);
                $incentive->setUser($this->userService->getUserEntity());
                $incentive->setSite($this->siteService->getSiteId());
                $incentive->setRdrId($result->incentiveId);
                $this->em->persist($incentive);
                $this->em->flush();
                $this->loggerService->log(Log::INCENTIVE_ADD, $incentive->getId());
                return true;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    public function amendIncentive($participantId, $incentiveForm)
    {
        $incentive = $this->getIncentiveFromFormData($incentiveForm);
        $postData = $this->getRdrObject($incentive, Incentive::AMEND);
        try {
            $response = $this->rdrApiService->put("rdr/v1/Participant/{$participantId}/Incentives", $postData);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->incentiveId)) {
                $now = new \DateTime();
                $incentive->setAmendedTs($now);
                $incentive->setAmendedUser($this->userService->getUserEntity());
                $this->em->persist($incentive);
                $this->em->flush();
                $this->loggerService->log(Log::INCENTIVE_EDIT, $incentive->getId());
                return true;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    public function cancelIncentive($participantId, $incentive)
    {
        $postData = $this->getRdrObject($incentive, Incentive::CANCEL);
        try {
            $response = $this->rdrApiService->put("rdr/v1/Participant/{$participantId}/Incentives", $postData);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->incentiveId)) {
                $now = new \DateTime();
                $incentive->setCancelledTs($now);
                $incentive->setCancelledUser($this->userService->getUserEntity());
                $this->em->persist($incentive);
                $this->em->flush();
                $this->loggerService->log(Log::INCENTIVE_REMOVE, $incentive->getId());
                return true;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    private function getIncentiveFromFormData($incentiveForm)
    {
        $incentive = $incentiveForm->getData();
        if ($incentive->getIncentiveAmount() === 'other') {
            $incentive->setIncentiveAmount($incentiveForm['other_incentive_amount']->getData());
        }
        if ($incentive->getIncentiveType() === 'promotional') {
            $incentive->setIncentiveAmount(0);
        }
        return $incentive;
    }

    public function hasAccess($participant): bool
    {
        return
            $participant->statusReason !== 'withdrawal' &&
            $participant->statusReason !== 'test-participant' &&
            !$this->siteService->isTestSite();
    }

    public function sendIncentivesToRdr()
    {
        $limit = $this->params->has('patient_status_queue_limit') ? intval($this->params->get('patient_status_queue_limit')) : 0;
        $incentiveImports = $this->em->getRepository(IncentiveImportRow::class)->getIncentiveImportRows($limit);
        $importIds = [];
        foreach ($incentiveImports as $incentiveImport) {
            if (!in_array($incentiveImport['import_id'], $importIds)) {
                $importIds[] = $incentiveImport['import_id'];
            }
            $incentiveImportRow = $this->em->getRepository(IncentiveImportRow::class)->find($incentiveImport['id']);
            if (!empty($incentiveImportRow)) {
                if ($this->createIncentive($incentiveImportRow->getParticipantId(), $incentiveImportRow)) {
                    $incentiveImportRow->setRdrStatus(IncentiveImport::STATUS_SUCCESS);
                    $this->em->persist($incentiveImportRow);
                    $this->em->flush();
                } else {
                    $this->logger->error("#{$incentiveImport['id']} failed sending to RDR: " . $this->rdrApiService->getLastError());
                    $rdrStatus = IncentiveImport::STATUS_OTHER_RDR_ERRORS;
                    if ($this->rdrApiService->getLastErrorCode() === 400) {
                        $rdrStatus = IncentiveImport::STATUS_INVALID_PARTICIPANT_ID;
                    } elseif ($this->rdrApiService->getLastErrorCode() === 500) {
                        $rdrStatus = IncentiveImport::STATUS_RDR_INTERNAL_SERVER_ERROR;
                    }
                    $incentiveImportRow->setRdrStatus($rdrStatus);
                    $this->em->persist($incentiveImportRow);
                    $this->em->flush();
                }
            }
        }
        // TODO Update import status
    }
}
