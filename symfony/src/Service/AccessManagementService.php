<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;
use Pmi\Audit\Log;

class AccessManagementService
{
    private $loggerService;
    private $env;
    private $params;
    private $twig;
    private $userService;

    public function __construct(
        LoggerService $loggerService,
        EnvironmentService $env,
        ParameterBagInterface $params,
        Environment $twig,
        UserService $userService
    ) {
        $this->loggerService = $loggerService;
        $this->env = $env;
        $this->params = $params;
        $this->twig = $twig;
        $this->userService = $userService;
    }

    public function sendEmail($group, $member, $memberLastDay, $currentTime): void
    {
        $message = new Message($this->env, $this->loggerService, $this->twig, $this->params);
        if ($this->params->has('feature.drcsupportemail') && $this->params->get('feature.drcsupportemail')) {
            $message
                ->setTo($this->params->get('feature.drcsupportemail'))
                ->render('group-member-removal-email', [
                    'group' => $group,
                    'member' => $member,
                    'memberLastDay' => $memberLastDay->format('m/d/Y'),
                    'loggedUser' => $this->userService->getUser()->getEmail(),
                    'currentTime' => $currentTime->format('Y-m-d H:i:s e')
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