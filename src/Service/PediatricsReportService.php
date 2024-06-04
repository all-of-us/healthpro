<?php

namespace App\Service;

use App\Entity\BloodPressureDiastolicHeightPercentile;
use App\Entity\BloodPressureSystolicHeightPercentile;
use App\Entity\HeartRateAge;
use App\Entity\Incentive;
use App\Entity\Measurement;
use Doctrine\ORM\EntityManagerInterface;

class PediatricsReportService
{
    public const BUCKET_NAME_STABLE = 'healthpro-stable-measurements-report';
    public const BUCKET_NAME_PROD = 'healthpro-prod-measurements-report';

    public const DEVIATION_AGE_RANGES = [
        [0, 11],
        [12, 47],
        [48, 72]
    ];
    public const DEVIATION_FIELDS = [
        'weight-protocol-modification',
        'height-protocol-modification',
        'head-circumference-protocol-modification',
        'waist-circumference-protocol-modification',
        'blood-pressure-protocol-modification'
    ];

    protected EntityManagerInterface $em;
    protected GcsBucketService $gcsBucketService;
    protected EnvironmentService $env;
    protected MeasurementService $measurementService;
    protected ParticipantSummaryService $participantSummaryService;

    public function __construct(
        EntityManagerInterface $em,
        GcsBucketService $gcsBucketService,
        EnvironmentService $env,
        MeasurementService $measurementService,
        ParticipantSummaryService $participantSummaryService
    ) {
        $this->em = $em;
        $this->measurementService = $measurementService;
        $this->gcsBucketService = $gcsBucketService;
        $this->participantSummaryService = $participantSummaryService;
        $this->env = $env;
    }

    public function generateIncentiveReport(): void
    {
        $incentives = $this->em->getRepository(Incentive::class)->getPediatricIncentivesForReport(new \DateTime('first day of last month'), new \DateTime('last day of last month'));
        $csvData = $this->getIncentiveReportCSVData($incentives);
    }

    public function getIncentiveReportCSVData($incentives)
    {
        $csvData = [];
        $csvData[] = ['Participant ID', 'Date Created', 'Site', 'Recipient', 'Date of Service', 'Incentive Occurrence',
            'Incentive Type', 'Gift Card Type', 'Appreciation Item Type', 'Appreciation Item Count', 'Incentive Amount', 'Declined'];;
        foreach ($incentives as $incentive) {
            $csvData[] = [$incentive['participantId'], $incentive['createdTs']->format('Y-m-d H:i:s'), $incentive['site'],
                $incentive['recipient'], $incentive['incentiveDateGiven']->format('Y-m-d'), $incentive['incentiveOccurrence'],
                $incentive['incentiveType'], $incentive['giftCardType'], $incentive['typeOfItem'], $incentive['numberOfItems'],
                $incentive['incentiveAmount'], $incentive['declined']];
        }
        return $csvData;
    }

    public function generateDeviationReport(): void
    {
        $evaluationsTotalData = [];
        foreach (self::DEVIATION_AGE_RANGES as $ageRange) {
            $newAgeRange = true;
            foreach (self::DEVIATION_FIELDS as $field) {
                $evaluations = $this->em->getRepository(Measurement::class)
                    ->getProtocolModificationCount(
                        new \DateTime('first day of last month'),
                        new \DateTime('last day of last month'),
                        $field,
                        $ageRange[0],
                        $ageRange[1]);
                if ($newAgeRange) {
                    $evaluationsTotalData[] = ["Protocol Deviations for age ranges {$ageRange[0]} to ${ageRange[1]} for field $field"];
                    $evaluationsTotalData[] = ['Modification Type', 'Count'];
                    $newAgeRange = false;
                }
                if (!empty($evaluations)) {
                    foreach ($evaluations as $evaluation) {
                        if (!empty($evaluation)) {
                            $evaluationsTotalData[] = [$evaluation['modificationType'], $evaluation['count']];
                        }
                    }
                }
            }
        }
        $this->generateCSVReport($evaluationsTotalData, 'Protocol_Deviations_Report-'.date('Ymd-His').'.csv');
    }

    public function generateActiveAlertReport(): void
    {
        $csvData = [];
        $bpSystolicCharts = $this->em->getRepository(BloodPressureSystolicHeightPercentile::class)->getChartsData();
        $bpDiastolicCharts = $this->em->getRepository(BloodPressureDiastolicHeightPercentile::class)->getChartsData();
        $heartRateAgeCharts = $this->em->getRepository(HeartRateAge::class)->getChartsData();
        foreach (self::DEVIATION_AGE_RANGES as $ageRange) {
            $measurements = $this->em->getRepository(Measurement::class)
                ->getActiveAlertsReportData(
                    new \DateTime('first day of last month'),
                    new \DateTime('last day of last month'),
                    $ageRange[0],
                    $ageRange[1]
                );
            foreach ($measurements as $measurement) {
                $measurementData = json_decode($measurement->getData(), true);
                $participant = $this->participantSummaryService->getParticipantById($measurement->getParticipantId());
                $growthChartsByAge = $measurement->getGrowthChartsByAge($measurement->getAgeInMonths());
                $HeartRateAlert = $this->getHeartRateAlert($measurementData, $measurement->getAgeInMonths(), $heartRateAgeCharts);
                $bpAlert = "";
                $test2 = "";
            }
        }
    }

    private function getHeartRateAlert($measurementData, $ageInMonths, $heartRateAgeCharts): array {
        $heartRates = $measurementData['heart-rate'];
        $heartCentiles = [];
        $alerts = [];
        foreach ($heartRateAgeCharts as $heartRateAgeChart) {
            if ($ageInMonths >= $heartRateAgeChart['startAge'] && $ageInMonths <= $heartRateAgeChart['endAge']) {
                $heartCentiles = $heartRateAgeChart;
            }
        }
        if ($ageInMonths < 12) {
            $heartRateOver175 = 0;
            $heartRateOver200 = 0;
            foreach ($heartRates as $heartRate) {
                if ($heartRate > 175) {
                    $heartRateOver175++;
                }
                if ($heartRate > 200) {
                    $heartRateOver200++;
                }
            }
            if ($heartRateOver200 > 0) {
                $alerts[] = "pME5";
            }
            if ($heartRateOver175 > 1) {
                $alerts[] = "pME5b";
            }
        } elseif ($ageInMonths > 12 && $ageInMonths < 36) {
            $heartRateOver175 = 0;
            foreach ($heartRates as $heartRate) {
                if ($heartRate > 175) {
                    $heartRateOver175++;
                }
            }
            if ($heartRateOver175 > 1) {
                $alerts[] = "pME5c";
            }
            if ($heartRateOver175 > 0) {
                $alerts[] = "pME5d";
            }
        }
        $centile1Count = 0;
        $centile99Count = 0;
        foreach ($heartRates as $heartRate) {
            if ($heartRate < $heartCentiles['centile1']) {
                $centile1Count++;
            }
            if ($heartRate > $heartCentiles['centile99']) {
                $centile99Count++;
            }
        }
        switch ($centile99Count) {
            case 2:
                $alerts[] = "pME6";
                break;
            case 1:
                $alerts[] = "pME6b";
                break;
        }
        switch ($centile1Count) {
            case 2:
                $alerts[] = "pME6c";
                break;
            case 1:
                $alerts[] = "pME6d";
                break;
        }
        return $alerts;
    }

    private function generateCSVReport($csvData, $csvTitle)
    {
        // Create a temporary stream to hold the CSV data
        //$tempStream = fopen('php://temp', 'w');
        $tempStream = fopen('test.csv', 'w');

        foreach ($csvData as $row) {
            fputcsv($tempStream, $row);
        }
        //$bucketName = $this->env->isProd() ? self::BUCKET_NAME_PROD : self::BUCKET_NAME_STABLE;
        //$this->gcsBucketService->uploadFile($bucketName, $tempStream, $csvTitle);
    }
}
