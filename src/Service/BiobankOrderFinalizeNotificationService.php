<?php

namespace App\Service;

use App\Audit\Log;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

class BiobankOrderFinalizeNotificationService
{
    protected $loggerService;
    protected $env;
    protected $params;
    protected $twig;


    public function __construct(
        LoggerService $loggerService,
        EnvironmentService $env,
        ParameterBagInterface $params,
        Environment $twig
    ) {
        $this->loggerService = $loggerService;
        $this->env = $env;
        $this->params = $params;
        $this->twig = $twig;
    }

    public function sendEmails(array $info, ?string $emails): void
    {
        if (!empty($emails)) {
            $emails = explode(',', $emails);
            $message = new Message($this->env, $this->loggerService, $this->twig, $this->params);
            $message
                ->setTo($emails)
                ->render('biobank-order-finalize', ['info' => $info])
                ->send();
            $this->loggerService->log(Log::BIOBANK_ORDER_FINALIZE_NOTIFY, [
                'status' => 'Notifications sent',
                'notified' => $emails
            ]);
        } else {
            $this->loggerService->log(Log::BIOBANK_ORDER_FINALIZE_NOTIFY, [
                'status' => 'No email address to notify'
            ]);
        }
    }
}
