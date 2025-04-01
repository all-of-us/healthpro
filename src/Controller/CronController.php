<?php

namespace App\Controller;

use App\Cache\DatastoreAdapter;
use App\Entity\Order;
use App\Service\BiobankNightlyReportService;
use App\Service\HFHRepairService;
use App\Service\MeasurementService;
use App\Service\Nph\NphDietPeriodStatusService;
use App\Service\Nph\NphDlwBackfillService;
use App\Service\PediatricsReportService;
use App\Service\SessionService;
use App\Service\SiteSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/cron')]
class CronController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    #[Route(path: '/ping-test', name: 'cron_ping_test')]
    public function pingTestAction()
    {
        return $this->json(['success' => true]);
    }

    #[Route(path: '/sites', name: 'cron_sites')]
    public function sitesAction(ParameterBagInterface $params, SiteSyncService $siteSyncService): Response
    {
        if (!$params->has('sites_use_rdr')) {
            return $this->json(['error' => 'RDR Awardee API disabled']);
        }
        $siteSyncService->sync();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/awardees-organizations', name: 'cron_awardees_organizations')]
    public function awardeesAndOrganizationsAction(ParameterBagInterface $params, SiteSyncService $siteSyncService): Response
    {
        if (!$params->has('sites_use_rdr')) {
            return $this->json(['error' => 'RDR Awardee API disabled']);
        }
        $siteSyncService->syncAwardees();
        $siteSyncService->syncOrganizations();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/delete-cache-keys', name: 'cron_delete_cache_keys')]
    public function deleteCacheKeysAction(ParameterBagInterface $params)
    {
        $cache = new DatastoreAdapter($params->get('ds_clean_up_limit'));
        $cache->prune();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/delete-session-keys', name: 'cron_delete_session_keys')]
    public function deleteSessionKeysAction(SessionService $sessionService)
    {
        $sessionService->deleteKeys();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/backfill-order-processed-time', name: 'backfill_order_processed_time')]
    public function backfillOrderProcessedTimeAction(ParameterBagInterface $params)
    {
        $limit = $params->has('backfill_order_limit') ? $params->get('backfill_order_limit') : 500;
        $orders = $this->em->getRepository(Order::class)->getBackfillOrders($limit);
        $batchSize = 50;
        foreach ($orders as $key => $order) {
            $processedSamplesTs = json_decode($order->getProcessedSamplesTs(), true);
            if (is_array($processedSamplesTs) && !empty($processedSamplesTs)) {
                $processedTs = new \DateTime();
                $processedTs->setTimestamp(max($processedSamplesTs));
                $order->setProcessedTs($processedTs);
            }
            $this->em->persist($order);
            if (($key % $batchSize) === 0) {
                $this->em->flush();
            }
        }
        $this->em->flush();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/repair-hfh-participants', name: 'cron_repair_hfh_participants')]
    public function repairHfhParticipantsAction(HFHRepairService $HFHRepairService)
    {
        $HFHRepairService->repairHFHParticipants();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/biobank-nightly-report', name: 'cron_biobank_nightly_report')]
    public function biobonkNightlyReport(BiobankNightlyReportService $biobankNightlyReportService): Response
    {
        $biobankNightlyReportService->generateNightlyReport();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/pediatrics-report-measurement-totals', name: 'cron_pediatrics_report_measurement_totals')]
    public function pediatricsReportMeasurementTotalsReportAction(PediatricsReportService $pediatricsReport, ParameterBagInterface $params): Response
    {
        $pediatricsReport->generateMeasurementTotalsReport();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/pediatrics-report-active-alerts', name: 'cron_pediatrics_report_active_alerts')]
    public function pediatricsReportActiveAlertsReportAction(PediatricsReportService $pediatricsReport, ParameterBagInterface $params): Response
    {
        $pediatricsReport->generateActiveAlertReport();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/pediatrics-report-deviations', name: 'cron_pediatrics_report_deviations')]
    public function pediatricsReportDeviationsReportAction(PediatricsReportService $pediatricsReport, ParameterBagInterface $params): Response
    {
        if ($params->has('startDate')) {
            $startDate = new \DateTime($params->get('startDate'));
        } else {
            $startDate = new \DateTime('first day of 3 months ago');
        }
        if ($params->has('endDate')) {
            $endDate = new \DateTime($params->get('endDate'));
        } else {
            $endDate = new \DateTime('last day of last month');
        }
        $pediatricsReport->generateDeviationReport($startDate, $endDate);
        return $this->json(['success' => true]);
    }

    #[Route(path: '/pediatrics-report-incentives', name: 'cron_pediatrics_report_incentives')]
    public function pediatricsReportIncentivesReportAction(PediatricsReportService $pediatricsReport, ParameterBagInterface $params): Response
    {
        if ($params->has('startDate')) {
            $startDate = new \DateTime($params->get('startDate'));
        } else {
            $startDate = new \DateTime('first day of 3 months ago');
        }
        if ($params->has('endDate')) {
            $endDate = new \DateTime($params->get('endDate'));
        } else {
            $endDate = new \DateTime('last day of last month');
        }
        $pediatricsReport->generateIncentiveReport($startDate, $endDate);
        return $this->json(['success' => true]);
    }

    #[Route(path: '/backfill-nph-diets-complete-status', name: 'cron_backfill_nph_orders_complete_status')]
    public function backfillNphOrdersCompleteStatus(NphDietPeriodStatusService $nphDietPeriodStatusService): Response
    {
        $nphDietPeriodStatusService->backfillDietPeriodCompleteStatus();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/backfill-nph-dlw', name: 'cron_backfill_nph_dlw')]
    public function backfillNphDlw(NphDlwBackfillService $backfill)
    {
        $backfill->backfillNphDlw();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/backfill-pediatrics-measurements-sex-at-birth', name: 'cron_backfill_pediatrics_measurements_sex_at_birth')]
    public function backfillPediatricMeasurementsSexAtBirth(MeasurementService $measurementService): Response
    {
        $measurementService->backfillMeasurementsSexAtBirth();
        return $this->json(['success' => true]);
    }
}
