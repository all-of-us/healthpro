<?php

namespace App\Controller;

use App\Service\DeceasedNotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s/cron")
 */
class CronController extends AbstractController
{
    /**
     * @Route("/deceased", name="cron_deceased")
     */
    public function index(DeceasedNotificationService $deceasedNotificationService)
    {
        $deceasedNotificationService->setDeceasedStatusType('approved');
        $deceasedNotificationService->sendEmails();
        return $this->json(['success' => true]);
    }
}
