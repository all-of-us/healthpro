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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class NphDietPeriodStatusService
{
    private const DEFAULT_USER_EMAIL = 'gwendolyn.raynor@pmi-ops.org';
    private const DEFAULT_BACKFILL_TS = '2022-06-20';
    private const DEFAULT_BACKFILL_LIMIT = 10;
    private EntityManagerInterface $em;
    private LoggerService $loggerService;
    private ParameterBagInterface $params;

    public function __construct(
        EntityManagerInterface $em,
        LoggerService $loggerService,
        ParameterBagInterface $params
    ) {
        $this->em = $em;
        $this->loggerService = $loggerService;
        $this->params = $params;
    }

    public function backfillDietPeriodCompleteStatus(): void
    {
        $backfillTs = $this->params->has('nph_diet_complete_backfill_ts') ? $this->params->get('nph_diet_complete_backfill_ts') : self::DEFAULT_BACKFILL_TS;
        $backfillLimit = $this->params->has('nph_diet_complete_backfill_limit') ? $this->params->get('nph_diet_complete_backfill_limit') : self::DEFAULT_BACKFILL_LIMIT;
        for ($i = 0; $i < $backfillLimit; $i++) {
            $participantData = $this->em->getRepository(NphOrder::class)->getParticipantNotInCronSampleProcessingStatusLog($backfillTs);
            if (!empty($participantData[0])) {
                $participantData = $participantData[0];
                $participantId = $participantData->getParticipantId();
                $module = $participantData->getModule();
                $visitPeriod = $this->extractPeriod($participantData->getVisitPeriod());
                $orders = $this->em->getRepository(NphOrder::class)->getOrdersByParticipantAndPeriod($participantId, $module, $visitPeriod);
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
                $cronNphSampleProcessingStatusLog->setPeriod($visitPeriod);
                $cronNphSampleProcessingStatusLog->setStatus($isDietComplete);
                $this->em->persist($cronNphSampleProcessingStatusLog);
                $this->em->flush();
                $this->loggerService->log(Log::CRON_NPH_SAMPLE_PROCESSING_LOG_CREATE, $cronNphSampleProcessingStatusLog->getId());
                if ($isDietComplete) {
                    $hasSampleProcessingStatus = $this->em->getRepository(NphSampleProcessingStatus::class)->getSampleProcessingStatus($participantId, $module, $visitPeriod);
                    if (empty($hasSampleProcessingStatus)) {
                        $nphSampleProcessingStatus = new NphSampleProcessingStatus();
                        $nphSampleProcessingStatus->setParticipantId($participantId);
                        $nphSampleProcessingStatus->setBiobankId($participantData->getBiobankId());
                        $nphSampleProcessingStatus->setModule($module);
                        $nphSampleProcessingStatus->setPeriod($visitPeriod);
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
                $this->em->refresh($cronNphSampleProcessingStatusLog);
            }
        }
    }

    private function getUser(): ?UserEntity
    {
        return $this->em->getRepository(UserEntity::class)->findOneBy(['email' => self::DEFAULT_USER_EMAIL]);
    }

    private function extractPeriod(string|null $visitPeriod): string|null
    {
        $pattern = '/(Period[123]|LMT)/';
        if (preg_match($pattern, $visitPeriod, $matches)) {
            return $matches[0];
        }
        return null;
    }
}
