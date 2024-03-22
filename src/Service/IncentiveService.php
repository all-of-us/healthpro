<?php

namespace App\Service;

use App\Audit\Log;
use App\Entity\Incentive;
use Doctrine\ORM\EntityManagerInterface;

class IncentiveService
{
    protected $rdrApiService;
    protected $siteService;
    protected $userService;
    protected $em;
    protected $loggerService;

    public function __construct(
        RdrApiService $rdrApiService,
        SiteService $siteService,
        UserService $userService,
        EntityManagerInterface $em,
        LoggerService $loggerService
    ) {
        $this->rdrApiService = $rdrApiService;
        $this->siteService = $siteService;
        $this->userService = $userService;
        $this->em = $em;
        $this->loggerService = $loggerService;
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
            $obj->dateGiven = $incentive->getIncentiveDateGiven()->format('Y-m-d\TH:i:s\Z');
            $obj->occurrence = $incentive->getOtherIncentiveOccurrence() ?? $incentive->getIncentiveOccurrence();
            $obj->incentiveType = $incentive->getOtherIncentiveType() ?: $incentive->getIncentiveType();
            $obj->incentiveRecipient = $incentive->getRecipient();
            if ($incentive->getIncentiveType() === Incentive::ITEM_OF_APPRECIATION) {
                $obj->appreciationItemType = $incentive->getTypeOfItem();
                $obj->appreciationItemCount = (string) $incentive->getNumberOfItems();
            }
            if ($incentive->getGiftCardType()) {
                $obj->giftcardType = $incentive->getGiftCardType();
            }
            $obj->incentiveRecipient = $incentive->getRecipient();
            if ($incentive->getTypeOfItem()) {
                $obj->appreciationItemType = $incentive->getTypeOfItem();
                $obj->appreciationItemCount = $incentive->getNumberOfItems();
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
            $result = $this->sendToRdr($participantId, $postData);
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

    public function hasAccess($participant): bool
    {
        return
            $participant->statusReason !== 'withdrawal' &&
            $participant->statusReason !== 'test-participant' &&
            !$this->siteService->isTestSite();
    }

    public function sendToRdr($participantId, $postData)
    {
        $response = $this->rdrApiService->post("rdr/v1/Participant/{$participantId}/Incentives", $postData);
        return json_decode($response->getBody()->getContents());
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
        if ($incentive->getRecipient() === Incentive::OTHER) {
            $incentive->setRecipient(Incentive::OTHER . ', ' . $incentiveForm['other_incentive_recipient']->getData());
        }
        if (!in_array($incentiveForm['recipient']->getData(), Incentive::$recipientChoices, true) && preg_match("/P\d{9}$/", $incentive->getRecipient())) {
            $incentive->setRecipient(Incentive::PEDIATRIC_GUARDIAN);
            $incentive->setRelatedParticipantRecipient($incentiveForm['recipient']->getData());
        }
        return $incentive;
    }
}
