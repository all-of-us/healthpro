<?php

namespace App\Controller;

use App\Repository\DeactivateLogRepository;
use App\Repository\WithdrawalLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s/admin/notifications")
 */
class NotificationsController extends AbstractController
{
    /**
     * @Route("/", name="admin_notifications")
     */
    public function index(WithdrawalLogRepository $withdrawalLogRepository, DeactivateLogRepository $deactivateLogRepository)
    {
        $withdrawalLogs = $withdrawalLogRepository->getWithdrawalLogs();
        $deactivateLogs = $deactivateLogRepository->getDeactivateLogs();
        return $this->render('admin/notifications.html.twig', [
            'withdrawalLogs' => $withdrawalLogs,
            'deactivateLogs' => $deactivateLogs
        ]);
    }
}
