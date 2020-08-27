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
        $withdrawalNotifications = $withdrawalLogRepository->getWithdrawalNotifications();
        $deactivatedNotifications = $deactivateLogRepository->getDeactivatedNotifications();
        return $this->render('admin/notifications.html.twig', [
            'withdrawalNotifications' => $withdrawalNotifications,
            'deactivatedNotifications' => $deactivatedNotifications
        ]);
    }
}
