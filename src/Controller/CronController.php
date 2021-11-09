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
use App\Cache\DatastoreAdapter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/cron", condition="request.headers.get('X-Appengine-Cron') === 'true'")
 */
class CronController extends AbstractController
{
    /**
     * @Route("/ping-test", name="cron_ping_test")
     */
    public function pingTestAction()
    {
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/deceased/{deceasedStatus}", name="cron_deceased")
     */
    public function index(DeceasedNotificationService $deceasedNotificationService, $deceasedStatus): Response
    {
        $deceasedNotificationService->setDeceasedStatusType($deceasedStatus);
        $deceasedNotificationService->sendEmails();
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/sites", name="cron_sites")
     */
    public function sitesAction(ParameterBagInterface $params, SiteSyncService $siteSyncService): Response
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
    public function awardeesAndOrganizationsAction(ParameterBagInterface $params, SiteSyncService $siteSyncService): Response
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
    public function ehrWithdrawal(EhrWithdrawalNotificationService $ehrWithdrawalNotificationService): Response
    {
        $ehrWithdrawalNotificationService->sendEmails();
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/withdrawal", name="cron_withdrawal")
     */
    public function withdrawalAction(WithdrawalNotificationService $withdrawalNotificationService): Response
    {
        $withdrawalNotificationService->sendEmails();
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/deactivate", name="cron_deactivate")
     */
    public function deactivateAction(DeactivateNotificationService $deactivateNotificationService): Response
    {
        $deactivateNotificationService->sendEmails();
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/missing-measurements-orders", name="cron_missing_measurements_orders")
     */
    public function missingMeasurementsOrdersAction(MissingMeasurementsAndOrdersNotificationService $missingMeasurementsAndOrdersNotificationService): Response
    {
        $missingMeasurementsAndOrdersNotificationService->sendEmails();
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/delete-cache-keys", name="cron_delete_cache_keys")
     */
    public function deleteCacheKeysAction(ParameterBagInterface $params)
    {
        $cache = new DatastoreAdapter($params->get('ds_clean_up_limit'));
        $cache->prune();
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/delete-session-keys", name="cron_delete_session_keys")
     */
    public function deleteSessionKeysAction(SessionService $sessionService)
    {
        $sessionService->deleteKeys();
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/delete-unconfirmed-patient-status-import-data", name="cron_delete_unconfirmed_patient_status_import_data")
     */
    public function deleteUnconfimedPatientStatusImportDataAction(PatientStatusService $patientStatusService)
    {
        $patientStatusService->deleteUnconfirmedImportData();
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/send-patient-status-rdr", name="cron_send_patient_status_rdr")
     */
    public function sendPatientStatusToRdrAction(PatientStatusService $patientStatusService)
    {
        $patientStatusService->sendPatientStatusToRdr();
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/resend-measurements-rdr", name="cron_resend_measurements_rdr")
     */
    public function resendMeasurementsToRdrAction(MeasurementQueueService $measurementQueueService)
    {
        $measurementQueueService->resendMeasurementsToRdr();
        return $this->json(['success' => true]);
    }

    /**
     * @Route("/backfill-order-processed-time", name="backfill_order_processed_time")
     */
    public function backfillOrderProcessedTimeAction(EntityManagerInterface $em, ParameterBagInterface $params)
    {
        $limit = $params->has('backfill_order_limit') ? $params->get('backfill_order_limit') : 500;
        $orders = $em->getRepository(Order::class)->getBackfillOrders($limit);
        $batchSize = 50;
        foreach ($orders as $key => $order) {
            $processedSamplesTs = json_decode($order->getProcessedSamplesTs(), true);
            if (is_array($processedSamplesTs) && !empty($processedSamplesTs)) {
                $processedTs = new \DateTime();
                $processedTs->setTimestamp(max($processedSamplesTs));
                $order->setProcessedTs($processedTs);
            }
            $em->persist($order);
            if (($key % $batchSize) === 0) {
                $em->flush();
            }
        }
        $em->flush();
        return $this->json(['success' => true]);
    }
}
