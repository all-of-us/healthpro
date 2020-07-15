<?php

namespace Pmi\Service;

use App\Entity\PatientStatusHistory;
use App\Entity\PatientStatusImport;
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
            SELECT pst.*,
                   psi.site,
                   psi.created_ts as authored,
                   psi.organization,
                   psi.awardee,
                   ps.id as patient_status_id,
                   u.id as user_id,
                   u.email as user_email
            FROM patient_status_temp pst
            INNER JOIN patient_status_import psi ON psi.id = pst.import_id AND psi.confirm = 1
            LEFT JOIN patient_status ps ON ps.participant_id = pst.participant_id AND psi.organization = ps.organization
            LEFT JOIN users u ON psi.user_id = u.id
            WHERE pst.rdr_status = 0
            ORDER BY pst.id ASC
            limit {$limit}
        ";
        $patientStatuses = $this->em->fetchAll($query, []);
        $patientStatusObj = new PatientStatus($this->app);
        $importIds = [];
        foreach ($patientStatuses as $patientStatus) {
            if (!in_array($patientStatus['import_id'], $importIds)) {
                $importIds[] = $patientStatus['import_id'];
            }
            $patientStatusObj->loadDataFromImport($patientStatus);
            if ($patientStatusObj->sendToRdr()) {
                if ($patientStatusObj->saveData()) {
                    $this->em->getRepository('patient_status_temp')->update($patientStatus['id'], ['rdr_status' => PatientStatusHistory::STATUS_SUCCESS]);
                }
            } else {
                $this->app['logger']->error("#{$patientStatus['id']} failed sending to RDR: " . $this->rdr->getLastError());
                $rdrStatus = PatientStatusHistory::STATUS_OTHER_RDR_ERRORS;
                if ($this->rdr->getLastErrorCode() === 400) {
                    $rdrStatus = PatientStatusHistory::STATUS_INVALID_PARTICIPANT_ID;
                } elseif ($this->rdr->getLastErrorCode() === 500) {
                    $rdrStatus = PatientStatusHistory::STATUS_RDR_INTERNAL_SERVER_ERROR;
                }
                $this->em->getRepository('patient_status_temp')->update($patientStatus['id'], ['rdr_status' => $rdrStatus]);
            }
        }
        // Update import status
        $this->updateImportStatus($importIds);
    }

    private function updateImportStatus($importIds)
    {
        foreach ($importIds as $importId) {
            $query = "SELECT COUNT(*) AS count FROM patient_status_temp WHERE import_id = :importId AND rdr_status = 0";
            $patientStatusHistory = $this->em->fetchAll($query, ['importId' => $importId]);
            if ($patientStatusHistory[0]['count'] == 0) {
                $query = "SELECT COUNT(*) AS count FROM patient_status_temp WHERE import_id = :importId AND rdr_status IN (2, 3, 4)";
                $patientStatusHistory = $this->em->fetchAll($query, ['importId' => $importId]);
                if ($patientStatusHistory[0]['count'] > 0) {
                    $this->em->getRepository('patient_status_import')->update($importId, ['import_status' => PatientStatusImport::COMPLETE_WITH_ERRORS]);
                } else {
                    $this->em->getRepository('patient_status_import')->update($importId, ['import_status' => PatientStatusImport::COMPLETE]);
                }
                $this->app->log(Log::PATIENT_STATUS_IMPORT_EDIT, $importId);
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
