<?php

namespace App\Service;

use App\Entity\DeceasedLog;
use App\Entity\Site;
use Doctrine\Persistence\ManagerRegistry;
use App\Audit\Log;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

class DeceasedNotificationService extends EmailNotificationService
{
    protected $type = 'Deceased';
    protected $time = 'deceasedAuthored';
    protected $status = 'deceasedStatus';
    protected $level = 'organization';
    protected $levelField = 'organizationId';
    protected $logEntity = 'App\Entity\DeceasedLog';

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
        $this->logRepository = $this->em->getRepository(DeceasedLog::class);
    }

    public function setDeceasedStatusType($deceasedStatus)
    {
        $this->deceasedStatus = $deceasedStatus;
        $this->render = $this->statusText = "deceased-{$deceasedStatus}";
        $this->log = $deceasedStatus === 'pending' ? Log::DECEASED_PENDING_NOTIFY : Log::DECEASED_APPROVED_NOTIFY;
    }

    protected function getSearchParams($id, $lastDeceased)
    {
        $searchParams = [
            'deceasedStatus' => strtoupper($this->deceasedStatus),
            'organization' => $id,
            '_sort:desc' => 'deceasedAuthored'
        ];
        if ($lastDeceased) {
            $filterTime = clone $lastDeceased;
            // Go back 1 month to make sure no participants are missed
            $filterTime->sub(new \DateInterval('P1M'));
            $searchParams['deceasedAuthored'] = 'ge' . $filterTime->format('Y-m-d\TH:i:s');
        }
        return $searchParams;
    }
}
