<?php

namespace App\Service;

use App\Entity\Incentive;
use App\Entity\PatientStatus;

class OnSiteDetailsReportingService
{
    public static $patientStatusExportHeaders = [
        'Date Created',
        'Participant ID',
        'User',
        'Site',
        'Patient Status',
        'Notes',
        'Imported'
    ];

    public static $patientStatusSortColumns = [
        'psh.createdTs',
        'ps.participantId',
        'u.email',
        's.name',
        'psh.status',
        'psh.comments'
    ];

    public static $incentiveSortColumns = [
        'i.createdTs',
        'i.participantId',
        'u.email',
        'i.incentiveDateGiven',
        'i.incentiveOccurrence',
        'i.incentiveType',
        'i.incentiveAmount',
        'i.declined',
        'i.notes'
    ];

    public function getAjaxData($patientStatuses): array
    {
        $rows = [];
        foreach ($patientStatuses as $patientStatus) {
            $row = [];
            $row['created'] = $patientStatus['createdTs']->format('m-d-Y');
            $row['participantId'] = $patientStatus['participantId'];
            $row['user'] = $patientStatus['email'];
            $row['site'] = $patientStatus['siteName'];
            $row['patientStatus'] = array_search($patientStatus['status'], PatientStatus::$onSitePatientStatus);
            $row['notes'] = $patientStatus['comments'];
            $row['importId'] = $patientStatus['importId'] ? 'Yes' : 'No';
            array_push($rows, $row);
        }
        return $rows;
    }

    public function getIncentiveTrackingAjaxData($incentives): array
    {
        $rows = [];
        foreach ($incentives as $incentive) {
            $row = [];
            $row['created'] = $incentive['createdTs']->format('m-d-Y');
            $row['participantId'] = $incentive['participantId'];
            $row['user'] = $incentive['email'];
            $row['dateOfService'] = $incentive['incentiveDateGiven']->format('m-d-Y');
            $row['occurrence'] = $incentive['incentiveOccurrence'] === 'other' ? 'Other, ' . $incentive['otherIncentiveOccurrence'] : array_search($incentive['incentiveOccurrence'], Incentive::$incentiveOccurrenceChoices);
            $type = array_search($incentive['incentiveType'], Incentive::$incentiveTypeChoices);
            if ($type === 'other') {
                $type = 'Other, ' . $incentive['otherIncentiveType'];
            } elseif ($type === 'gift_card') {
                $type = 'Gift Card, ' . $incentive['giftCardType'];
            }
            $row['incentiveType'] = $type;
            $row['amount'] = $incentive['incentiveAmount'] ? '$' . $incentive['incentiveAmount'] : '';
            $row['declined'] = $incentive['declined'] ? 'Yes' : 'No';
            $row['notes'] = $incentive['notes'];
            $type = '';
            if ($incentive['importId'] && $incentive['amendedUser']) {
                $type = 'import_amend';
            } elseif ($incentive['importId']) {
                $type = 'import';
            } elseif ($incentive['amendedUser']) {
                $type = 'amend';
            }
            $row['type'] = $type;
            array_push($rows, $row);
        }
        return $rows;
    }
}
