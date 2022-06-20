<?php

namespace App\Service;

class OnSiteDetailsReportingService
{
    public function getAjaxData($patientStatuses): array
    {
        $rows = [];
        foreach ($patientStatuses as $patientStatus) {
            $patientStatusHistory = $patientStatus->getHistory();
            $row = [];
            $row['created'] = $patientStatusHistory->getCreatedTs()->format('m-d-Y');
            $row['participantId'] = $patientStatus->getParticipantId();
            //TODO get user email
            $row['user'] = $patientStatusHistory->getUserId();
            $row['site'] = $patientStatusHistory->getSite();
            $row['patientStatus'] = $patientStatusHistory->getStatus();
            $row['notes'] = $patientStatusHistory->getComments();
            array_push($rows, $row);
        }
        return $rows;
    }
}
