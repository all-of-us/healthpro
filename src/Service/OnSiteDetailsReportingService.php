<?php

namespace App\Service;

class OnSiteDetailsReportingService
{
    public static $patientStatusExportHeaders = [
        'Date Created',
        'Participant ID',
        'User',
        'Site',
        'Patient Status',
        'Notes'
    ];

    public static $patientStatusSortColumns = [
        'psh.createdTs',
        'ps.participantId',
        'u.email',
        'psh.site',
        'psh.status',
        'psh.comments'
    ];

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
            $row['importId'] = $patientStatus['importId'];
            array_push($rows, $row);
        }
        return $rows;
    }
}
