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
    }

    public function sendPatientStatusToRdr()
    {
        $limit = $this->app->getConfig('patient_status_queue_limit');
        $patientStatuses = $this->em->getRepository('patient_status')->fetchBySql("id not in (select patient_status_id from rdr_patient_status_log) limit 0, $limit");
        error_log(print_r($patientStatuses, true));
        $patientStatusObj = new PatientStatus($this->app);
        foreach ($patientStatuses as $patientStatus) {
            $query = "
                SELECT psh.id,
                       psh.site,
                       psh.status,
                       psh.comments,
                       psh.created_ts as authored,
                       u.email as user_email
                FROM patient_status_history psh
                LEFT JOIN users u ON psh.user_id = u.id
                WHERE psh.patient_status_id = :patientStatusId
                  AND rdr_ts is null
                ORDER BY psh.id ASC
            ";
            $patientStatusHistories = $this->em->fetchAll($query, [
                'patientStatusId' => $patientStatus['id']
            ]);
            foreach ($patientStatusHistories as $patientStatusHistory) {
                $patientStatusObj->loadDataFromDb($patientStatus, $patientStatusHistory);
                if ($patientStatusObj->sendToRdr()) {
                    $this->em->getRepository('patient_status_history')->update($patientStatusHistory['id'], ['rdr_ts' => new \DateTime()]);
                } else {
                    syslog(LOG_ERR, "#{$patientStatusHistory['id']} failed sending to RDR: " .$this->rdr->getLastError());
                }
            }
            // Log entry in database after all the patient status history records has been successfully sent to rdr
            $this->em->getRepository('rdr_patient_status_log')->insert([
                    'patient_status_id' => $patientStatus['id'],
                    'created_ts' => new \DateTime()
                ]
            );
        }
    }
}
