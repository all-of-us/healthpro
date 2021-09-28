<?php

namespace App\Controller;

use App\Entity\Order;
use App\Service\DeactivateNotificationService;
use App\Service\DeceasedNotificationService;
use App\Service\EhrWithdrawalNotificationService;
use App\Service\MeasurementQueueService;
use App\Service\MissingMeasurementsAndOrdersNotificationService;
use App\Service\PatientStatusService;
use App\Service\SessionService;
use App\Service\SiteSyncService;
use App\Service\WithdrawalNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Pmi\Cache\DatastoreAdapter;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s/cloud-tasks", condition="request.headers.get('X-Appengine-Taskname') matches '/[A-Za-z0-9\-\_]+/'")
 */
class CloudTasksController extends AbstractController
{
    /**
     * @Route("/ping-test", name="cloud_tasks_ping_test")
     */
    public function pingTest(): Response
    {
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/sync-site-email", name="cloud_tasks_sync_site_email")
     */
    public function syncSiteEmail(SiteSyncService $siteSyncService, Request $request): Response
    {
        $siteId = $request->request->get('site_id');
        try {
            $siteSyncService->syncSiteEmail($siteId);
        } catch (\Exception $e) {
            throw $this->createNotFoundException($e->getMessage());
        }
        return $this->json(['success' => true]);
    }
}
