<?php

namespace App\Service\Nph;

use App\Audit\Log;
use App\Entity\CronNphSampleProcessingStatusLog;
use App\Entity\NphOrder;
use App\Entity\NphSample;
use App\Entity\NphSampleProcessingStatus;
use App\Entity\User as UserEntity;
use App\Service\LoggerService;
use Doctrine\ORM\EntityManagerInterface;

class NphDietPeriodStatusService
{
    private const DEFAULT_USER_EMAIL = 'gwendolyn.raynor@pmi-ops.org';
    private const LIMIT = 10;
    private $em;
    private $loggerService;

    public function __construct(
        EntityManagerInterface $em,
        LoggerService $loggerService,
    ) {
        $this->em = $em;
        $this->loggerService = $loggerService;
    }

    public function backfillDietPeriodCompleteStatus()
    {
        for ($i = 0; $i < self::LIMIT; $i++) {
            $participantData = $this->em->getRepository(NphOrder::class)->getParticipantNotInCronSampleProcessingStatusLog();
            if (!empty($participantData[0])) {
                $participantData = $participantData[0];
                $participantId = $participantData->getParticipantId();
                $module = $participantData->getModule();
                $visitType = $participantData->getVisitType();
                $orders = $this->em->getRepository(NphOrder::class)->findBy(['participantId' => $participantId, 'module' => $module, 'visitType' => $visitType]);
                $isDietComplete = !empty($orders) ? 1 : 0;
                foreach ($orders as $order) {
                    $samples = $order->getNphSamples();
                    foreach ($samples as $sample) {
                        if ($sample->getFinalizedTs() === null && $sample->getModifyType() !== NphSample::CANCEL) {
                            $isDietComplete = 0;
                            break 2;
                        }
                    }
                }
                $cronNphSampleProcessingStatusLog = new CronNphSampleProcessingStatusLog();
                $cronNphSampleProcessingStatusLog->setParticipantId($participantId);
                $cronNphSampleProcessingStatusLog->setModule($module);
                $cronNphSampleProcessingStatusLog->setPeriod($visitType);
                $cronNphSampleProcessingStatusLog->setStatus($isDietComplete);
                $this->em->persist($cronNphSampleProcessingStatusLog);
                $this->em->flush();
                $this->loggerService->log(Log::CRON_NPH_SAMPLE_PROCESSING_LOG_CREATE, $cronNphSampleProcessingStatusLog->getId());
                if ($isDietComplete) {
                    $hasSampleProcessingStatus = $this->em->getRepository(NphSampleProcessingStatus::class)->getSampleProcessingStatus($participantId, $module, $visitType);
                    if (empty($hasSampleProcessingStatus)) {
                        $nphSampleProcessingStatus = new NphSampleProcessingStatus();
                        $nphSampleProcessingStatus->setParticipantId($participantId);
                        $nphSampleProcessingStatus->setBiobankId($participantData->getBiobankId());
                        $nphSampleProcessingStatus->setModule($module);
                        $nphSampleProcessingStatus->setPeriod($visitType);
                        $nphSampleProcessingStatus->setUser($this->getUser());
                        $nphSampleProcessingStatus->setSite($participantData->getSite());
                        $nphSampleProcessingStatus->setStatus(1);
                        $nphSampleProcessingStatus->setModifyType('finalized');
                        $nphSampleProcessingStatus->setModifiedTs(new \DateTime());
                        $nphSampleProcessingStatus->setModifiedTimezoneId($participantData->getCreatedTimezoneId());
                        $nphSampleProcessingStatus->setIncompleteSamples(0);
                        $this->em->persist($nphSampleProcessingStatus);
                        $this->em->flush();
                        $this->loggerService->log(Log::NPH_SAMPLE_PROCESSING_STATUS_CREATE, $nphSampleProcessingStatus->getId());
                    }
                }
            }
        }
    }

    private function getUser(): ?UserEntity
    {
        return $this->em->getRepository(UserEntity::class)->findOneBy(['email' => self::DEFAULT_USER_EMAIL]);
    }
}
