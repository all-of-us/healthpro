<?php

namespace App\Service;

use App\Entity\Incentive;
use App\Entity\PatientStatus;

class OnSiteDetailsReportingService
{
    protected $participantSummaryService;

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
        'Occurrence',
        'Type',
        'Amount',
        'Declined?',
        'Notes',
        'Imported',
        'Amended'
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
        'onsiteIdVerificationTime',
        'participantId',
        'onsiteIdVerificationUser',
        'onsiteIdVerificationType',
        'onsiteIdVerificationVisitType'
    ];

    public function __construct(ParticipantSummaryService $participantSummaryService)
    {
        $this->participantSummaryService = $participantSummaryService;
    }

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
            $row['occurrence'] = $occurrence;
            $type = '';
            if ($incentive['incentiveType']) {
                if ($incentive['incentiveType'] === Incentive::OTHER) {
                    $type = 'Other, ' . $incentive['otherIncentiveType'];
                } elseif ($incentive['incentiveType'] === Incentive::GIFT_CARD) {
                    $type = 'Gift Card, ' . $incentive['giftCardType'];
                } else {
                    $type = array_search($incentive['incentiveType'], Incentive::$incentiveTypeChoices);
                }
            }
            $row['incentiveType'] = $type;
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

    public function getIdVerificationAjaxData($siteId, $params): array
    {
        $rdrParams['site'] = $siteId;
        $rdrParams['_count'] = $params['length'] ?? 10;
        $rdrParams['_offset'] = $params['start'] ?? 0;
        if (!empty($params['order'][0])) {
            $sortColumnIndex = $params['order'][0]['column'];
            $sortColumnName = self::$idVerificationSortColumns[$sortColumnIndex];
            $sortDir = $params['order'][0]['dir'];
            if ($sortDir === 'asc') {
                $rdrParams['_sort'] = $sortColumnName;
            } else {
                $rdrParams['_sort:desc'] = $sortColumnName;
            }
        }
        $participantSummaries = $this->participantSummaryService->listWorkQueueParticipantSummaries($rdrParams);
        $rows = [];
        foreach ($participantSummaries as $participantSummary) {
            if (isset($participantSummary->resource)) {
                $participantResource = $participantSummary->resource;
                $row = [];
                $row['created'] = $participantResource->onsiteIdVerificationTime ?? '';
                $row['participantId'] = $participantResource->participantId;
                $row['user'] = '';
                $row['verificationType'] = $participantResource->onsiteIdVerificationType ?? '';
                $row['visitType'] = $participantResource->onsiteIdVerificationVisitType ?? '';
                $rows[] = $row;
            }

        }
        return $rows;
    }

    public function getIdVerificationsTotal(): ?int
    {
        return $this->participantSummaryService->getTotal();
    }
}
