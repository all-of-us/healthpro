<?php

namespace App\Service;

use App\Audit\Log;
use App\Entity\DeactivateLog;
use App\Entity\Site;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

class DeactivateNotificationService extends EmailNotificationService
{
    protected $type = 'Deactivate';
    protected $time = 'suspensionTime';
    protected $level = 'awardee';
    protected $levelField = 'hpoId';
    protected $logEntity = 'App\Entity\DeactivateLog';
    protected $statusText = 'Deactivated';
    protected $log = Log::DEACTIVATE_NOTIFY;
    protected $render = 'deactivates';

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
        $this->logRepository = $this->em->getRepository(DeactivateLog::class);
    }

    protected function getSearchParams($id, $lastDeactivate)
    {
        $searchParams = [
            'awardee' => $id,
            'withdrawnStatus' => 'NOT_WITHDRAWN',
            'suspensionStatus' => 'NO_CONTACT',
            '_sort:desc' => 'suspensionTime'
        ];
        if ($lastDeactivate) {
            $filterTime = clone $lastDeactivate;
            // Go back 1 day to make sure no participants are missed
            $filterTime->sub(new \DateInterval('P1D'));
            $searchParams['suspensionTime'] = 'ge' . $filterTime->format('Y-m-d\TH:i:s');
        }
        return $searchParams;
    }
}
