<?php
namespace Pmi\Service;

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
                   ps.participant_id,
                   ps.organization,
                   ps.awardee,
                   u.email as user_email
            FROM patient_status_history psh
            INNER JOIN patient_status ps ON ps.id = psh.patient_status_id
            LEFT JOIN users u ON psh.user_id = u.id
            WHERE psh.rdr_ts is null
            ORDER BY psh.id ASC
            limit {$limit}
        ";
        $patientStatusHistories = $this->em->fetchAll($query, []);
        $patientStatusObj = new PatientStatus($this->app);
        foreach ($patientStatusHistories as $patientStatusHistory) {
            $patientStatusObj->loadDataFromDb($patientStatusHistory);
            if ($patientStatusObj->sendToRdr()) {
                $this->em->getRepository('patient_status_history')->update($patientStatusHistory['id'], ['rdr_ts' => new \DateTime()]);
                $this->app->log(Log::PATIENT_STATUS_HISTORY_EDIT, [
                    'id' => $patientStatusHistory['id']
                ]);
            } else {
                syslog(LOG_ERR, "#{$patientStatusHistory['id']} failed sending to RDR: " . $this->rdr->getLastError());
            }
        }
    }
}
