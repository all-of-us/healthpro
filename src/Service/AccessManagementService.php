<?php

namespace App\Service;

use App\Audit\Log;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

class AccessManagementService
{
    private LoggerService $loggerService;
    private EnvironmentService $env;
    private ParameterBagInterface $params;
    private Environment $twig;
    private UserService $userService;
    private ContextTemplateService $contextTemplateService;

    public function __construct(
        LoggerService $loggerService,
        EnvironmentService $env,
        ParameterBagInterface $params,
        Environment $twig,
        UserService $userService,
        ContextTemplateService $contextTemplateService
    ) {
        $this->loggerService = $loggerService;
        $this->env = $env;
        $this->params = $params;
        $this->twig = $twig;
        $this->userService = $userService;
        $this->contextTemplateService = $contextTemplateService;
    }

    public function sendEmail(
        string $group,
        string $member,
        \DateTime $memberLastDay,
        \DateTime $currentTime,
        string $attestation = null
    ): void {
        $message = new Message($this->env, $this->loggerService, $this->twig, $this->params);
        if ($this->params->has('feature.drcsupportemail') && $this->params->get('feature.drcsupportemail')) {
            $message
                ->setTo($this->params->get('feature.drcsupportemail'))
                ->render('group-member-removal-email', [
                    'group' => $group,
                    'member' => $member,
                    'memberLastDay' => $memberLastDay->format('m/d/Y'),
                    'loggedUser' => $this->userService->getUser()->getEmail(),
                    'currentTime' => $currentTime->format('Y-m-d H:i:s e'),
                    'attestation' => $attestation,
                    'programDisplayText' => $this->contextTemplateService->getCurrentProgramDisplayText()
                ])
                ->send();
            $this->loggerService->log(Log::GROUP_MEMBER_REMOVE_NOTIFY, [
                'group' => $group,
                'member' => $member,
                'memberLastDay' => $memberLastDay
            ]);
        }
    }
}
