<?php

namespace App\Controller;

use App\Service\DeceasedNotificationService;
use App\Service\EhrWithdrawalNotificationService;
use App\Service\SiteSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/s/cron")
 */
class CronController extends AbstractController
{
    /**
     * @Route("/deceased/{deceasedStatus}", name="cron_deceased")
     */
    public function index(DeceasedNotificationService $deceasedNotificationService, $deceasedStatus)
    {
        $deceasedNotificationService->setDeceasedStatusType($deceasedStatus);
        $deceasedNotificationService->sendEmails();
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/sites", name="cron_sites")
     */
    public function sitesAction(ParameterBagInterface $params, SiteSyncService $siteSyncService)
    {
        if (!$params->has('sites_use_rdr')) {
            return $this->json(['error' => 'RDR Awardee API disabled']);
        }
        $siteSyncService->sync();
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/awardees-organizations", name="cron_awardees_organizations")
     */
    public function awardeesAndOrganizationsAction(ParameterBagInterface $params, SiteSyncService $siteSyncService)
    {
        if (!$params->has('sites_use_rdr')) {
            return $this->json(['error' => 'RDR Awardee API disabled']);
        }
        $siteSyncService->syncAwardees();
        $siteSyncService->syncOrganizations();
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/ehr-withdrawal", name="cron_ehr_withdrawal")
     */
    public function ehrWithdrawal(EhrWithdrawalNotificationService $ehrWithdrawalNotificationService)
    {
        $ehrWithdrawalNotificationService->sendEmails();
        return $this->json(['success' => true]);
    }
}
