<?php

namespace App\Controller;

use App\Repository\DeactivateLogRepository;
use App\Repository\WithdrawalLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/notifications")
 */
class NotificationsController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

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
