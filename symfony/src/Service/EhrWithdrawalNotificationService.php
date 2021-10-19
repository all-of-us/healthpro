<?php

namespace App\Service;

use App\Entity\EhrWithdrawalLog;
use App\Entity\Site;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;
use App\Audit\Log;

class EhrWithdrawalNotificationService extends EmailNotificationService
{
    protected $type = 'EhrWithdrawal';
    protected $time = 'consentForElectronicHealthRecordsAuthored';
    protected $level = 'awardee';
    protected $levelField = 'awardeeId';
    protected $logEntity = 'App\Entity\EhrWithdrawalLog';
    protected $statusText = 'EHR withdrawn';
    protected $log = Log::EHR_WITHDRAWAL_NOTIFY;
    protected $render = 'ehr-withdrawal';
    protected $launchDate = '2017-01-01T00:00:00';

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
            'consentForElectronicHealthRecords' => 'SUBMITTED_NO_CONSENT',
            'consentForElectronicHealthRecordsFirstYesAuthored' => 'ge' . $this->launchDate,
            '_sort:desc' => 'consentForElectronicHealthRecordsTime'
        ];
        if ($lastEhrWithdrawn) {
            $filterTime = clone $lastEhrWithdrawn;
            // Go back 1 day to make sure no participants are missed
            $filterTime->sub(new \DateInterval('P1D'));
            $searchParams['consentForElectronicHealthRecordsTime'] = 'ge' . $filterTime->format('Y-m-d\TH:i:s');
        }
        return $searchParams;
    }
}
