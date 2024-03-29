<?php

namespace App\Service;

use App\Entity\Incentive;
use App\Entity\PatientStatus;
use App\Form\IdVerificationType;

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

    public static $incentiveExportHeaders = [
        'Date Created',
        'Participant ID',
        'User',
        'Date of Service',
        'Recipient',
        'Occurrence',
        'Type',
        'Number Of Items',
        'Type Of Item',
        'Amount',
        'Declined?',
        'Notes',
        'Imported',
        'Amended'
    ];

    public static $idVerificationExportHeaders = [
        'Date Created',
        'Participant ID',
        'User',
        'Verification Type',
        'Visit Type',
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

    public static $idVerificationSortColumns = [
        'idv.createdTs',
        'idv.participantId',
        'u.email',
        'idv.verificationType',
        'idv.visitType'
    ];

    public function getPatientStatusAjaxData($patientStatuses): array
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
            $row['siteId'] = $patientStatus['siteId'];
            array_push($rows, $row);
        }
        return $rows;
    }

    public function getIncentiveTrackingAjaxData($incentives, $export = false): array
    {
        $rows = [];
        foreach ($incentives as $incentive) {
            $row = [];
            $row['created'] = $incentive['createdTs']->format('m-d-Y');
            $row['participantId'] = $incentive['participantId'];
            $row['user'] = $incentive['email'];
            $row['dateOfService'] = $incentive['incentiveDateGiven']->format('m-d-Y');
            $occurrence = '';
            if ($incentive['incentiveOccurrence']) {
                $occurrence = $incentive['incentiveOccurrence'] === Incentive::OTHER ? 'Other, ' .
                    $incentive['otherIncentiveOccurrence'] : array_search(
                        $incentive['incentiveOccurrence'],
                        Incentive::$incentiveOccurrenceChoices
                    );
            }
            $row['recipient'] = array_search($incentive['recipient'], Incentive::$recipientChoices);
            $row['occurrence'] = $occurrence;
            $type = '';
            if ($incentive['incentiveType']) {
                if ($incentive['incentiveType'] === Incentive::OTHER) {
                    $type = 'Other, ' . $incentive['otherIncentiveType'];
                } elseif ($incentive['incentiveType'] === Incentive::GIFT_CARD) {
                    $type = 'Gift Card, ' . $incentive['giftCardType'];
                } elseif ($incentive['incentiveType'] === Incentive::ITEM_OF_APPRECIATION) {
                    $type = 'Item of Appreciation, ' . $incentive['typeOfItem'] . ', ' .
                        $incentive['numberOfItems'];
                } else {
                    $type = array_search($incentive['incentiveType'], Incentive::$incentiveTypeChoices);
                }
            }
            $row['incentiveType'] = $type;
            $row['numberOfItems'] = $incentive['numberOfItems'];
            $row['typeOfItem'] = $incentive['typeOfItem'];
            $row['amount'] = $incentive['incentiveAmount'] ? '$' . $incentive['incentiveAmount'] : '';
            $row['declined'] = $incentive['declined'] ? 'Yes' : 'No';
            $row['notes'] = $incentive['notes'];
            if ($export) {
                $row['imported'] = $incentive['importId'] ? 'Yes' : 'No';
                $row['amended'] = $incentive['amendedUser'] ? 'Yes' : 'No';
            } else {
                $type = '';
                if ($incentive['importId'] && $incentive['amendedUser']) {
                    $type = 'import_amend';
                } elseif ($incentive['importId']) {
                    $type = 'import';
                } elseif ($incentive['amendedUser']) {
                    $type = 'amend';
                }
                $row['type'] = $type;
            }
            array_push($rows, $row);
        }
        return $rows;
    }

    public function getIdVerificationAjaxData($idVerifications, $export = false): array
    {
        $rows = [];
        foreach ($idVerifications as $idVerification) {
            $row = [];
            $row['created'] = $idVerification['createdTs']->format('m-d-Y');
            $row['participantId'] = $idVerification['participantId'];
            $row['user'] = $idVerification['email'];
            $row['verificationType'] = $idVerification['verificationType'] ? array_search(
                $idVerification['verificationType'],
                IdVerificationType::$idVerificationChoices['verificationType']
            ) : '';
            $row['visitType'] = $idVerification['visitType'] ? array_search(
                $idVerification['visitType'],
                IdVerificationType::$idVerificationChoices['visitType']
            ) : '';
            if ($export) {
                $row['imported'] = $idVerification['importId'] ? 'Yes' : 'No';
            } else {
                $row['type'] = $idVerification['importId'] ? 'import' : '';
            }
            $row['guardianVerified'] = (bool) $idVerification['guardianVerified'];
            array_push($rows, $row);
        }
        return $rows;
    }
}
