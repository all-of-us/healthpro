<?php

namespace App\Controller;

use App\Cache\DatastoreAdapter;
use App\Entity\Order;
use App\Service\BiobankNightlyReportService;
use App\Service\DeceasedNotificationService;
use App\Service\HFHRepairService;
use App\Service\IdVerificationImportService;
use App\Service\IdVerificationService;
use App\Service\IncentiveImportService;
use App\Service\MeasurementQueueService;
use App\Service\MissingMeasurementsAndOrdersNotificationService;
use App\Service\Nph\NphDietPeriodStatusService;
use App\Service\Nph\NphDlwBackfillService;
use App\Service\PatientStatusService;
use App\Service\PediatricsReportService;
use App\Service\SessionService;
use App\Service\SiteSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
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

    #[Route(path: '/deceased/{deceasedStatus}', name: 'cron_deceased')]
    public function index(DeceasedNotificationService $deceasedNotificationService, $deceasedStatus): Response
    {
        $deceasedNotificationService->setDeceasedStatusType($deceasedStatus);
        $deceasedNotificationService->sendEmails();
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

    #[Route(path: '/missing-measurements-orders', name: 'cron_missing_measurements_orders')]
    public function missingMeasurementsOrdersAction(MissingMeasurementsAndOrdersNotificationService $missingMeasurementsAndOrdersNotificationService): Response
    {
        $missingMeasurementsAndOrdersNotificationService->sendEmails();
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

    #[Route(path: '/delete-unconfirmed-patient-status-import-data', name: 'cron_delete_unconfirmed_patient_status_import_data')]
    public function deleteUnconfimedPatientStatusImportDataAction(PatientStatusService $patientStatusService)
    {
        $patientStatusService->deleteUnconfirmedImportData();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/send-patient-status-rdr', name: 'cron_send_patient_status_rdr')]
    public function sendPatientStatusToRdrAction(PatientStatusService $patientStatusService)
    {
        $patientStatusService->sendPatientStatusToRdr();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/resend-measurements-rdr', name: 'cron_resend_measurements_rdr')]
    public function resendMeasurementsToRdrAction(MeasurementQueueService $measurementQueueService)
    {
        $measurementQueueService->resendMeasurementsToRdr();
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

    #[Route(path: '/send-incentives-rdr', name: 'cron_send_incentives_rdr')]
    public function sendIncentivesToRdrAction(IncentiveImportService $incentiveImportService)
    {
        $incentiveImportService->sendIncentivesToRdr();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/delete-unconfirmed-incentives-import-data', name: 'cron_delete_unconfirmed_incentives_import_data')]
    public function deleteUnconfimedIncentivesImportDataAction(IncentiveImportService $incentiveImportService)
    {
        $incentiveImportService->deleteUnconfirmedImportData();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/send-id-verifications-rdr', name: 'cron_send_id_verifications_rdr')]
    public function sendIdVerificationsToRdrAction(IdVerificationImportService $idVerificationImportService)
    {
        $idVerificationImportService->sendIdVerificationsToRdr();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/delete-unconfirmed-id-verifications-import-data', name: 'cron_delete_unconfirmed_id_verifications_import_data')]
    public function deleteUnconfimedIdVerificationsImportDataAction(IdVerificationImportService $idVerificationImportService)
    {
        $idVerificationImportService->deleteUnconfirmedImportData();
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

    #[Route(path: '/backfill-id-verifications-rdr', name: 'cron_backfill_id_verifications_rdr')]
    public function backfillIdVerificationsRdrAction(IdVerificationService $idVerificationService): Response
    {
        $idVerificationService->backfillIdVerificationsRdr();
        return $this->json(['success' => true]);
    }

    #[Route(path: '/pediatrics-report', name: 'cron_pediatrics_report')]
    public function pediatricsReportAction(PediatricsReportService $pediatricsReport, ParameterBagInterface $params): Response
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
        $pediatricsReport->generateMeasurementTotalsReport($startDate, $endDate);
        $pediatricsReport->generateActiveAlertReport($startDate, $endDate);
        $pediatricsReport->generateDeviationReport($startDate, $endDate);
        $pediatricsReport->generateIncentiveReport($startDate, $endDate);
        return $this->json(['success' => true]);
    }

    #[Route(path: '/pediatrics-report-measurement-totals', name: 'cron_pediatrics_report_measurement_totals')]
    public function pediatricsReportMeasurementTotalsReportAction(PediatricsReportService $pediatricsReport, ParameterBagInterface $params): Response
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
        $pediatricsReport->generateMeasurementTotalsReport($startDate, $endDate);
        return $this->json(['success' => true]);
    }

    #[Route(path: '/pediatrics-report-active-alerts', name: 'cron_pediatrics_report_active_alerts')]
    public function pediatricsReportActiveAlertsReportAction(PediatricsReportService $pediatricsReport, ParameterBagInterface $params): Response
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
        $pediatricsReport->generateActiveAlertReport($startDate, $endDate);
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
}
