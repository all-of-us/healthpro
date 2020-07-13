<?php

namespace Pmi\Service;

use App\Entity\PatientStatusHistory;
use Pmi\Audit\Log;
use Pmi\PatientStatus\PatientStatus;

class PatientStatusService
{
    protected $app;
    protected $db;
    protected $rdr;

    public function __construct($app)
    {
        $this->app = $app;
        $this->db = $app['db'];
        $this->em = $app['em'];
        $this->rdr = $app['pmi.drc.participants'];
    }

    public function sendPatientStatusToRdr()
    {
        $limit = $this->app->getConfig('patient_status_queue_limit');
        $query = "
            SELECT psh.id,
                   psh.patient_status_id,
                   psh.site,
                   psh.status,
                   psh.comments,
                   psh.created_ts as authored,
                   psh.import_id,
                   ps.participant_id,
                   ps.organization,
                   ps.awardee,
                   u.email as user_email
            FROM patient_status_history psh
            INNER JOIN patient_status ps ON ps.id = psh.patient_status_id
            LEFT JOIN users u ON psh.user_id = u.id
            WHERE psh.rdr_ts is null and psh.rdr_status = 0
            ORDER BY psh.id ASC
            limit {$limit}
        ";
        $patientStatusHistories = $this->em->fetchAll($query, []);
        $patientStatusObj = new PatientStatus($this->app);
        $importIds = [];
        foreach ($patientStatusHistories as $patientStatusHistory) {
            if (!in_array($patientStatusHistory['import_id'], $importIds)) {
                $importIds[] = $patientStatusHistory['import_id'];
            }
            $patientStatusObj->loadDataFromDb($patientStatusHistory);
            if ($patientStatusObj->sendToRdr()) {
                // Set rdr_status = 1 for success
                $this->em->getRepository('patient_status_history')->update($patientStatusHistory['id'], ['rdr_ts' => new \DateTime(), 'rdr_status' => PatientStatusHistory::STATUS_1]);
                $this->app->log(Log::PATIENT_STATUS_HISTORY_EDIT, [
                    'id' => $patientStatusHistory['id']
                ]);
            } else {
                $this->app['logger']->error("#{$patientStatusHistory['id']} failed sending to RDR: " . $this->rdr->getLastError());
                // RDR status
                // 2 = RDR 400 (Invalid participant id)
                // 3 = RDR 500 (Invalid patient status)
                // 4 = Other RDR errors
                $rdrStatus = PatientStatusHistory::STATUS_4;
                if ($this->rdr->getLastErrorCode() === 400) {
                    $rdrStatus = PatientStatusHistory::STATUS_2;
                } elseif ($this->rdr->getLastErrorCode() === 500) {
                    $rdrStatus = PatientStatusHistory::STATUS_3;
                }
                $this->em->getRepository('patient_status_history')->update($patientStatusHistory['id'], ['rdr_status' => $rdrStatus]);
            }
        }
        // Update import status
        $this->updateImportStatus($importIds);
    }

    private function updateImportStatus($importIds)
    {
        foreach ($importIds as $importId) {
            $query = "SELECT COUNT(*) AS count FROM patient_status_history WHERE import_id = :importId AND rdr_status = 0";
            $patientStatusHistory = $this->em->fetchAll($query, ['importId' => $importId]);
            if ($patientStatusHistory[0]['count'] == 0) {
                $query = "SELECT COUNT(*) AS count FROM patient_status_history WHERE import_id = :importId AND rdr_status IN (2, 3, 4)";
                $patientStatusHistory = $this->em->fetchAll($query, ['importId' => $importId]);
                // Import Status
                // 1 = Complete with no errors
                // 2 = Complete with errors
                if ($patientStatusHistory[0]['count'] > 0) {
                    $this->em->getRepository('patient_status_import')->update($importId, ['import_status' => 2]);
                } else {
                    $this->em->getRepository('patient_status_import')->update($importId, ['import_status' => 1]);
                }
            }
        }
    }

    public function deletePatientStatusTempData()
    {
        $date = (new \DateTime('UTC'))->modify('-1 hours');
        $date = $date->format('Y-m-d H:i:s');
        $this->db->query("DELETE pst FROM patient_status_temp pst inner join patient_status_import psi on pst.import_id = psi.id where psi.created_ts < '$date'");
    }
}
