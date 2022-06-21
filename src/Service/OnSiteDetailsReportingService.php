<?php

namespace App\Service;

class OnSiteDetailsReportingService
{
    public function getAjaxData($patientStatuses): array
    {
        $rows = [];
        foreach ($patientStatuses as $patientStatus) {
            $row = [];
            $row['created'] = $patientStatus['createdTs']->format('m-d-Y');
            $row['participantId'] = $patientStatus['participantId'];
            $row['user'] = $patientStatus['email'];
            $row['site'] = $patientStatus['site'];
            $row['patientStatus'] = $patientStatus['status'];
            $row['notes'] = $patientStatus['comments'];
            array_push($rows, $row);
        }
        return $rows;
    }
}
