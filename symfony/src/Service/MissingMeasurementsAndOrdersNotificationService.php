<?php

namespace App\Service;

use App\Entity\Measurement;
use App\Entity\MissingNotificationLog;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;
use Pmi\Audit\Log;

class MissingMeasurementsAndOrdersNotificationService
{
    protected $em;
    protected $loggerService;
    protected $env;
    protected $params;
    protected $twig;

    public const MEASUREMENT_TYPE = 'measurement';
    public const ORDER_TYPE = 'order';

    public function __construct(
        EntityManagerInterface $em,
        LoggerService $loggerService,
        EnvironmentService $env,
        ParameterBagInterface $params,
        Environment $twig
    ) {
        $this->em = $em;
        $this->loggerService = $loggerService;
        $this->env = $env;
        $this->params = $params;
        $this->twig = $twig;
    }

    public function sendEmails(): void
    {
        $missingEvaluations = $this->em->getRepository(Measurement::class)->getUnloggedMissingMeasurements();
        foreach ($missingEvaluations as $evaluation) {
            $this->insertRecords($evaluation['id'], MissingNotificationLog::MEASUREMENT_TYPE);
        }

        $missingOrders = $this->em->getRepository(Order::class)->getUnloggedMissingOrders();
        foreach ($missingOrders as $order) {
            $this->insertRecords($order['id'], MissingNotificationLog::ORDER_TYPE);
        }

        $emails = $this->params->has('missing_notify_email_address') ? $this->params->get('missing_notify_email_address') : null;
        if (!empty($emails) && (!empty($missingEvaluations) || !empty($missingOrders))) {
            $emails = explode(',', $emails);
            $message = new Message($this->env, $this->loggerService, $this->twig, $this->params);
            $message
                ->setTo($emails)
                ->render('missing-measurements-orders', [])
                ->send();
            $this->loggerService->log(Log::MISSING_MEASUREMENTS_ORDERS_NOTIFY, [
                'status' => 'Notifications sent',
                'notified' => $emails
            ]);
        }
    }

    public function insertRecords($id, $type): void
    {
        $missingNotificationLog = new MissingNotificationLog();
        $missingNotificationLog->setType($type);
        $missingNotificationLog->setRecordId($id);
        $this->em->persist($missingNotificationLog);
        $this->em->flush();
    }
}
