<?php

namespace App\Service;

use App\Entity\DeceasedLog;
use App\Entity\Site;
use Doctrine\Persistence\ManagerRegistry;
use Pmi\Audit\Log;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

class ProblemNotificationService extends EmailNotificationService
{
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

    protected function getSearchParams($id, $lastDeactivate)
    {
        // Not implemented for ProblemNotificationService
    }

    public function sendProblemReportEmail($problemId)
    {
        if ($this->params->has('dvAdminEmail')) {
            $email = $this->params->get('dvAdminEmail');
        }
        if (isset($email) && !empty($email)) {
            $loginUrl = '/';
            if ($this->params->has('login_url')) {
                $loginUrl = $this->params->get('login_url');
            }
            $message = new Message($this->env, $this->loggerService, $this->twig, $this->params);
            $message
                ->setTo($email)
                ->render('problem', ['loginUrl' => $loginUrl])
                ->send();
            $this->loggerService->log(Log::PROBLEM_NOTIFIY, [
                'problemId' => $problemId,
                'status' => 'Unactipated problem notification sent',
                'notified' => $email
            ]);
        } else {
            $this->loggerService->log(Log::PROBLEM_NOTIFIY, [
                'problemId' => $problemId,
                'status' => 'Unactipated problem but no one to notify'
            ]);
        }
    }
}
