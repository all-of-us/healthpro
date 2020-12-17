<?php

namespace App\Service;

use App\Entity\EhrWithdrawalLog;
use App\Entity\Site;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

class EhrWithdrawalNotificationService extends EmailNotificationService
{
    protected $type = 'EhrWithdrawal';
    protected $time = 'consentForElectronicHealthRecordsAuthored';
    protected $level = 'awardee';
    protected $levelField = 'Awardee';
    protected $filterSummaries = true;

    public function __construct(
        ManagerRegistry $managerRegistry,
        ParticipantSummaryService $participantSummaryService,
        LoggerService $loggerService,
        EnvironmentService $env,
        ParameterBagInterface $params,
        Environment $twig
    ) {
        $this->em = $managerRegistry->getManager();
        $this->managerRegistry = $managerRegistry;
        $this->participantSummaryService = $participantSummaryService;
        $this->loggerService = $loggerService;
        $this->env = $env;
        $this->params = $params;
        $this->twig = $twig;
        $this->siteRepository = $this->em->getRepository(Site::class);
        $this->logRepository = $this->em->getRepository(EhrWithdrawalLog::class);
    }

    protected function getSearchParams($id, $lastEhrWithdrawn)
    {
        $searchParams = [
            'awardee' => $id,
            '_sort:desc' => 'consentForElectronicHealthRecordsAuthored'
        ];
        if ($lastEhrWithdrawn) {
            $filterTime = clone $lastEhrWithdrawn;
            // Go back 1 month to make sure no participants are missed
            $filterTime->sub(new \DateInterval('P1M'));
            $searchParams['consentForElectronicHealthRecordsAuthored'] = 'ge' . $filterTime->format('Y-m-d\TH:i:s');
        }
        return $searchParams;
    }

    protected function filterSummaries($summaries)
    {
        $newSummaries = [];
        foreach ($summaries as $summary) {
            if (!empty($summary->resource->consentForElectronicHealthRecordsFirstYesAuthored) && ($summary->resource->consentForElectronicHealthRecords === 'SUBMITTED_NOT_SURE' || $summary->resource->consentForElectronicHealthRecords === 'SUBMITTED_NO_CONSENT')) {
                $newSummaries[] = $summary;
            }
        }
        return $newSummaries;
    }
}
