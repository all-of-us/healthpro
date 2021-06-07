<?php

namespace App\Service;

use App\Entity\Site;
use App\Entity\WithdrawalLog;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;
use Pmi\Audit\Log;

class WithdrawalNotificationService extends EmailNotificationService
{
    protected $type = 'Withdrawal';
    protected $time = 'withdrawalTime';
    protected $level = 'awardee';
    protected $levelField = 'hpoId';
    protected $logEntity = 'App\Entity\WithdrawalLog';
    protected $statusText = 'Withdrawn';
    protected $log = Log::WITHDRAWAL_NOTIFY;
    protected $render = 'withdrawals';

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
        $this->logRepository = $this->em->getRepository(WithdrawalLog::class);
    }

    protected function getSearchParams($id, $lastWithdrawal)
    {
        $searchParams = [
            'awardee' => $id,
            'withdrawalStatus' => 'NO_USE',
            '_sort:desc' => 'withdrawalTime'
        ];
        if ($lastWithdrawal) {
            $filterTime = clone $lastWithdrawal;
            // Go back 1 day to make sure no participants are missed
            $filterTime->sub(new \DateInterval('P1D'));
            $searchParams['withdrawalTime'] = 'ge' . $filterTime->format('Y-m-d\TH:i:s');
        }
        return $searchParams;
    }
}
