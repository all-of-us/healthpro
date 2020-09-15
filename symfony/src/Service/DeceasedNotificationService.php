<?php

namespace App\Service;

use App\Repository\SiteRepository;
use App\Repository\DeceasedLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Pmi\Audit\Log;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

class DeceasedNotificationService extends EmailNotificationService
{
    protected $em;
    protected $participantSummaryService;
    protected $loggerService;
    protected $env;
    protected $params;
    protected $twig;
    protected $siteRepository;
    protected $logRepository;
    protected $type = 'deceased';
    protected $render = 'deceased';
    protected $time = 'deceasedTime';
    protected $log = Log::DECEASED_NOTIFY;
    protected $statusText = 'deceased';

    public function __construct(
        EntityManagerInterface $em,
        ParticipantSummaryService $participantSummaryService,
        LoggerService $loggerService,
        EnvironmentService $env,
        ParameterBagInterface $params,
        Environment $twig,
        SiteRepository $siteRepository,
        DeceasedLogRepository $deceasedLogRepository
    ) {
        $this->em = $em;
        $this->participantSummaryService = $participantSummaryService;
        $this->loggerService = $loggerService;
        $this->env = $env;
        $this->params = $params;
        $this->twig = $twig;
        $this->siteRepository = $siteRepository;
        $this->logRepository = $deceasedLogRepository;
    }

    protected function getSearchParams($id, $lastDeceased)
    {
        $searchParams = [
            'deceasedStatus' => 'PENDING',
            'hpoId' => $id,
            '_sort:desc' => 'deceasedAuthored'
        ];
        if ($lastDeceased) {
            $filterTime = clone $lastDeceased;
            // Go back 1 day to make sure no participants are missed
            $filterTime->sub(new \DateInterval('P1D'));
            $searchParams['deceasedAuthored'] = 'ge' . $filterTime->format('Y-m-d\TH:i:s');
        }
        return $searchParams;
    }
}
