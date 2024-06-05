<?php

namespace App\Service;

use App\Entity\BloodPressureDiastolicHeightPercentile;
use App\Entity\BloodPressureSystolicHeightPercentile;
use App\Entity\HeartRateAge;
use App\Entity\Incentive;
use App\Entity\Measurement;
use App\Entity\ZScores;
use Doctrine\ORM\EntityManagerInterface;

class PediatricsReportService
{
    public const BUCKET_NAME_STABLE = 'healthpro-stable-measurements-report';
    public const BUCKET_NAME_PROD = 'healthpro-prod-measurements-report';

    public const DEVIATION_AGE_RANGES = [
        '<1' => [0, 11],
        '1-3' => [12, 47],
        '4-6' => [48, 72]
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
    protected array $zScores;

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
        $this->zScores = $this->em->getRepository(ZScores::class)->getChartsData();
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
            'Incentive Type', 'Gift Card Type', 'Appreciation Item Type', 'Appreciation Item Count', 'Incentive Amount', 'Declined'];
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
        foreach (self::DEVIATION_AGE_RANGES as $ageText => $ageRange) {
            $newAgeRange = true;
            foreach (self::DEVIATION_FIELDS as $field) {
                $evaluations = $this->em->getRepository(Measurement::class)
                    ->getProtocolModificationCount(
                        new \DateTime('first day of last month'),
                        new \DateTime('last day of last month'),
                        $field,
                        $ageRange[0],
                        $ageRange[1]
                    );
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
        $this->generateCSVReport($evaluationsTotalData, 'Protocol_Deviations_Report-' . date('Ymd-His') . '.csv');
    }

    public function generateActiveAlertReport(): void
    {
        $csvData = [];
        $bpSystolicCharts = $this->em->getRepository(BloodPressureSystolicHeightPercentile::class)->getChartsData();
        $bpDiastolicCharts = $this->em->getRepository(BloodPressureDiastolicHeightPercentile::class)->getChartsData();
        $heartRateAgeCharts = $this->em->getRepository(HeartRateAge::class)->getChartsData();
        $alertsData = [];
        foreach (self::DEVIATION_AGE_RANGES as $ageText => $ageRange) {
            $measurements = $this->em->getRepository(Measurement::class)
                ->getActiveAlertsReportData(
                    new \DateTime('first day of last month'),
                    new \DateTime('last day of last month'),
                    $ageRange[0],
                    $ageRange[1]
                );
            /**
             * @var Measurement $measurement
             */
            foreach ($measurements as $measurement) {
                $measurementData = json_decode($measurement->getData(), true);
                $participant = $this->participantSummaryService->getParticipantById($measurement->getParticipantId());
                $growthChartsByAge = $measurement->getGrowthChartsByAge((int) $measurement->getAgeInMonths());
                $headCircumferenceChart = null;
                if ($growthChartsByAge['headCircumferenceForAgeCharts'] !== null) {
                    $headCircumferenceChart = $this->em->getRepository($growthChartsByAge['headCircumferenceForAgeCharts'])->getChartsData($participant->sexAtBirth);
                } else {
                    $headCircumferenceChart = [];
                }
                if ($growthChartsByAge['weightForAgeCharts'] !== null) {
                    $weightChart = $this->em->getRepository($growthChartsByAge['weightForAgeCharts'])->getChartsData($participant->sexAtBirth);
                } else {
                    $weightChart = [];
                }
                if ($growthChartsByAge['weightForLengthCharts'] !== null) {
                    $weightForLengthChart = $this->em->getRepository($growthChartsByAge['weightForLengthCharts'])->getChartsData($participant->sexAtBirth);
                } else {
                    $weightForLengthChart = [];
                }
                if ($growthChartsByAge['bmiForAgeCharts'] !== null) {
                    $bmiChart = $this->em->getRepository($growthChartsByAge['bmiForAgeCharts'])->getChartsData($participant->sexAtBirth);
                } else {
                    $bmiChart = [];
                }
                $heartRateAlert = $this->getHeartRateAlert($measurementData, $measurement->getAgeInMonths(), $heartRateAgeCharts);
                $headCircumferenceAlert = $this->getHeadCircumferenceAlert($measurement, $measurementData, $measurement->getAgeInMonths(), $participant->sexAtBirth, $headCircumferenceChart);
                $irregularHeartRhythmAlert = $this->getIrregularHeartRhythmAlert($measurementData);
                $weightAlert = $this->getWeightAlert($measurement, $measurementData, $measurement->getAgeInMonths(), $weightChart, $participant->sexAtBirth);
                $weightForLengthAlert = $this->getWeightForLengthAlert($measurement, $measurementData, $weightForLengthChart, $participant->sexAtBirth);
                $bmiAlert = $this->getBMIAlert($measurement, $measurementData, $measurement->getAgeInMonths(), $bmiChart, $participant->sexAtBirth);
                $heightAlert = $this->getHeightAlert($measurementData, $measurement->getAgeInMonths());
                $waistAlert = $this->getWaistAlert($measurementData, $measurement->getAgeInMonths());
                if (!array_key_exists($ageText, $alertsData)) {
                    $alertsData[$ageText] = [];
                    $alertsData[$ageText]['Heart Rate'] = [];
                    $alertsData[$ageText]['Head Circumference'] = [];
                    $alertsData[$ageText]['Irregular Heart Rhythm'] = [];
                    $alertsData[$ageText]['Weight'] = [];
                    $alertsData[$ageText]['Weight for Length'] = [];
                    $alertsData[$ageText]['BMI'] = [];
                    $alertsData[$ageText]['Height/Length'] = [];
                }
                if (!array_key_exists($heartRateAlert, $alertsData[$ageText]['Heart Rate'])) {
                    $alertsData[$ageText]['Heart Rate'][$heartRateAlert] = 0;
                } else {
                    $alertsData[$ageText]['Heart Rate'][$heartRateAlert]++;
                }
                if (!array_key_exists($headCircumferenceAlert, $alertsData[$ageText]['Head Circumference'])) {
                    $alertsData[$ageText]['Head Circumference'][$headCircumferenceAlert] = 0;
                } else {
                    $alertsData[$ageText]['Head Circumference'][$headCircumferenceAlert]++;
                }
                if (!array_key_exists($irregularHeartRhythmAlert, $alertsData[$ageText]['Irregular Heart Rhythm'])) {
                    $alertsData[$ageText]['Irregular Heart Rhythm'][$irregularHeartRhythmAlert] = 0;
                } else {
                    $alertsData[$ageText]['Irregular Heart Rhythm'][$irregularHeartRhythmAlert]++;
                }
                if (!array_key_exists($weightAlert, $alertsData[$ageText]['Weight'])) {
                    $alertsData[$ageText]['Weight'][$weightAlert] = 0;
                } else {
                    $alertsData[$ageText]['Weight'][$weightAlert]++;
                }
                if (!array_key_exists($weightForLengthAlert, $alertsData[$ageText]['Weight for Length'])) {
                    $alertsData[$ageText]['Weight for Length'][$weightForLengthAlert] = 0;
                } else {
                    $alertsData[$ageText]['Weight for Length'][$weightForLengthAlert]++;
                }
                if (!array_key_exists($bmiAlert, $alertsData[$ageText]['BMI'])) {
                    $alertsData[$ageText]['BMI'][$bmiAlert] = 0;
                } else {
                    $alertsData[$ageText]['BMI'][$bmiAlert]++;
                }
                if (!array_key_exists($heightAlert, $alertsData[$ageText]['Height/Length'])) {
                    $alertsData[$ageText]['Height/Length'][$heightAlert] = 0;
                } else {
                    $alertsData[$ageText]['Height/Length'][$heightAlert]++;
                }
            }
        }
    }

    public function generateMeasurementsReport(): void
    {
        foreach (self::DEVIATION_AGE_RANGES as $ageText => $ageRange) {
            $measurements = $this->em->getRepository(Measurement::class)
                ->getMeasurementsReportData(
                    new \DateTime('first day of last month'),
                    new \DateTime('last day of last month'),
                    $ageRange[0],
                    $ageRange[1]
                );
            $csvData = $this->getMeasurementsReportCSVData($measurements);
            $this->generateCSVReport($csvData, 'Measurements_Report-' . date('Ymd-His') . '.csv');
        }
    }

    private function getHeartRateAlert(array $measurementData, float $ageInMonths, array $heartRateAgeCharts): string
    {
        $heartRates = $measurementData['heart-rate'];
        $heartCentiles = [];
        foreach ($heartRateAgeCharts as $heartRateAgeChart) {
            if ($ageInMonths >= $heartRateAgeChart['startAge'] && $ageInMonths <= $heartRateAgeChart['endAge']) {
                $heartCentiles = $heartRateAgeChart;
            }
        }
        if ($ageInMonths <= 1) {
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
                return 'pME5';
            }
            if ($heartRateOver175 > 1) {
                return 'pME5b';
            }
        }
        if ($ageInMonths > 1 && $ageInMonths < 6) {
            $heartRateOver175 = 0;
            foreach ($heartRates as $heartRate) {
                if ($heartRate > 175) {
                    $heartRateOver175++;
                }
            }
            if ($heartRateOver175 > 1) {
                return 'pME5c';
            }
            if ($heartRateOver175 > 0) {
                return 'pME5d';
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
                return 'pME6';
            case 1:
                return 'pME6b';
        }
        switch ($centile1Count) {
            case 2:
                return 'pME6c';
            case 1:
                return 'pME6d';
        }
        if ($ageInMonths <= 35) {
            $heartRateLessThan85 = 0;
            $heartRateOver205 = 0;
            foreach ($heartRates as $heartRate) {
                if ($heartRate < 85) {
                    $heartRateLessThan85++;
                }
                if ($heartRate > 205) {
                    $heartRateOver205++;
                }
            }
            if ($heartRateLessThan85 >= 1) {
                return 'pSC19';
            }
            if ($heartRateOver205 >= 1) {
                return 'pSC20';
            }
        } elseif ($ageInMonths > 35 && $ageInMonths <= 83) {
            $heartRateLessThan60 = 0;
            $heartRateOver200 = 0;
            foreach ($heartRates as $heartRate) {
                if ($heartRate < 60) {
                    $heartRateLessThan60++;
                }
                if ($heartRate > 200) {
                    $heartRateOver200++;
                }
            }
            if ($heartRateLessThan60 >= 1) {
                return 'pSC19';
            }
            if ($heartRateOver200 >= 1) {
                return 'pSC20';
            }
        } elseif ($ageInMonths > 83) {
            $heartRateLessThan50 = 0;
            $heartRateOver140 = 0;
            foreach ($heartRates as $heartRate) {
                if ($heartRate < 50) {
                    $heartRateLessThan50++;
                }
                if ($heartRate > 140) {
                    $heartRateOver140++;
                }
            }
            if ($heartRateLessThan50 >= 1) {
                return 'pSC21';
            }
            if ($heartRateOver140 >= 1) {
                return 'pSC22';
            }
        }
        return '';
    }
    private function getHeadCircumferenceAlert(Measurement $measurement, array $measurementData, float $ageInMonths, int $sex, ?array $growthChartsByAge = null): string
    {
        $headCircumferences = array_key_exists('head-circumference', $measurementData) ? $measurementData['head-circumference'] : [];
        $headCircumferenceChart = [];
        if ($growthChartsByAge !== null) {
            foreach ($growthChartsByAge as $growthChart) {
                if (floor($growthChart['month']) === $ageInMonths) {
                    if ($sex === 0 || ($sex === $growthChart['sex'])) {
                        $headCircumferenceChart[] = $growthChart;
                    }
                }
            }
        }

        if ($ageInMonths <= 24) {
            foreach ($headCircumferenceChart as $chart) {
                $overRangeCount = 0;
                foreach ($headCircumferences as $headCircumference) {
                    if (!empty($headCircumference)) {
                        $zScore = $measurement->calculateZScore($headCircumference, $chart['L'], $chart['M'], $chart['S']);
                        if ($zScore > 2.5) {
                            $overRangeCount++;
                        }
                    }
                }
                if ($overRangeCount === 1) {
                    return 'pME7b';
                }
                if ($overRangeCount >= 2) {
                    return 'pME7a';
                }
            }
        } else {
            foreach ($headCircumferences as $headCircumference) {
                if ($headCircumference < 29) {
                    return 'pSC10';
                }
                if ($headCircumference > 55) {
                    return 'pSC11';
                }
            }
        }
        return '';
    }
    private function getIrregularHeartRhythmAlert(array $measurementData): string
    {
        foreach ($measurementData['irregular-heart-rate'] as $irregularHeartRate) {
            if ($irregularHeartRate) {
                return 'pME8';
            }
        }
        return '';
    }
    private function getWeightAlert(Measurement $measurement, array $measurementData, float $ageInMonths, array $growthChartsByAge, int $sex): string
    {
        $weights = array_key_exists('weight', $measurementData) ? $measurementData['weight'] : [];
        $weightChart = [];
        if ($growthChartsByAge !== null) {
            foreach ($growthChartsByAge as $growthChart) {
                if (floor($growthChart['month']) === $ageInMonths) {
                    if ($sex === 0 || ($sex === $growthChart['sex'])) {
                        $weightChart[] = $growthChart;
                    }
                }
            }
        }
        if ($ageInMonths <= 35) {
            foreach ($weights as $weight) {
                if ($weight < 1) {
                    return 'pSC6';
                }
                if ($weight > 18) {
                    return 'pSC7';
                }
            }
        } elseif ($ageInMonths >= 36 && $ageInMonths <= 83) {
            foreach ($weights as $weight) {
                if ($weight < 7) {
                    return 'pSC8';
                }
                if ($weight > 35) {
                    return 'pSC9';
                }
            }
        }
        foreach ($weightChart as $chart) {
            $underRangeCount = 0;
            foreach ($weights as $weight) {
                if (!empty($weight)) {
                    $zScore = $measurement->calculateZScore($weight, $chart['L'], $chart['M'], $chart['S']);
                    $percentile = $measurement->calculatePercentile($zScore, $this->zScores);
                    if ($percentile < 3) {
                        $underRangeCount++;
                    }
                }
            }
            if ($underRangeCount === 1) {
                return 'pME10';
            }
            if ($underRangeCount >= 2) {
                return 'pME9';
            }
        }
        return '';
    }
    private function getWeightForLengthAlert(Measurement $measurement, array $measurementData, array $growthChartsByAge, int $sex): string
    {
        $weights = array_key_exists('weight', $measurementData) ? $measurementData['weight'] : [];
        $heights = array_key_exists('height', $measurementData) ? $measurementData['height'] : [];
        if (count($heights) === 0) {
            return '';
        }
        $averageLength = round(array_sum($heights) / count($heights));
        $weightForLengthChart = [];
        if ($growthChartsByAge !== null) {
            foreach ($growthChartsByAge as $growthChart) {
                if (round($growthChart['length']) === $averageLength) {
                    if ($sex === 0 || ($sex === $growthChart['sex'])) {
                        $weightForLengthChart[] = $growthChart;
                    }
                }
            }
        }
        foreach ($weightForLengthChart as $chart) {
            $underRangeCount = 0;
            foreach ($weights as $weight) {
                if (!empty($weight)) {
                    $zScore = $measurement->calculateZScore($weight, $chart['L'], $chart['M'], $chart['S']);
                    $percentile = $measurement->calculatePercentile($zScore, $this->zScores);
                    if ($percentile < 2.3) {
                        $underRangeCount++;
                    }
                }
            }
            if ($underRangeCount === 1) {
                return 'pME11';
            }
            if ($underRangeCount >= 2) {
                return 'pME12';
            }
        }
        return '';
    }
    private function getBMIAlert(Measurement $measurement, array $measurementData, float $ageInMonths, array $growthChartsByAge, int $sex): string
    {
        $weights = array_key_exists('weight', $measurementData) ? $measurementData['weight'] : [];
        $heights = array_key_exists('height', $measurementData) ? $measurementData['height'] : [];
        $bmiChart = [];
        $bmis = [];
        foreach ($weights as $weight) {
            if (!empty($weight)) {
                foreach ($heights as $height) {
                    if (!empty($height)) {
                        $bmis[] = $weight / (($height / 100) * ($height / 100));
                    }
                }
            }
        }
        if ($growthChartsByAge !== null) {
            foreach ($growthChartsByAge as $growthChart) {
                if (round($growthChart['month']) === $ageInMonths) {
                    if ($sex === 0 || ($sex === $growthChart['sex'])) {
                        $bmiChart[] = $growthChart;
                    }
                }
            }
        }
        if ($ageInMonths > 5) {
            foreach ($bmiChart as $chart) {
                $underRangeCount = 0;
                foreach ($bmis as $bmi) {
                    if (!empty($bmi)) {
                        $zScore = $measurement->calculateZScore($bmi, $chart['L'], $chart['M'], $chart['S']);
                        $percentile = $measurement->calculatePercentile($zScore, $this->zScores);
                        if ($percentile < 2.3) {
                            $underRangeCount++;
                        }
                    }
                }
                if ($underRangeCount === 1) {
                    return 'pME14';
                }
                if ($underRangeCount >= 2) {
                    return 'pME13';
                }
            }
        }
        if ($ageInMonths > 60 && $ageInMonths <= 83) {
            foreach ($bmis as $bmi) {
                if ($bmi < 10) {
                    return 'pSC25';
                }
                if ($bmi > 31) {
                    return 'pSC26';
                }
            }
        }
        return '';
    }
    private function getHeightAlert(array $measurementData, float $ageInMonths): string
    {
        $heights = array_key_exists('height', $measurementData) ? $measurementData['height'] : [];
        foreach ($heights as $height) {
            if ($height >= 0.0 && $height <= 2.3) {
                return 'pSC1';
            }

            if ($ageInMonths <= 35) {
                if ($height < 42) {
                    return 'pSC2';
                }
                if ($height > 109) {
                    return 'pSC3';
                }
            }

            if ($ageInMonths > 35 && $ageInMonths <= 83) {
                if ($height < 80) {
                    return 'pSC4';
                }
                if ($height > 134) {
                    return 'pSC5';
                }
            }
        }
        return '';
    }
    private function getWaistAlert(array $measurementData, float $ageInMonths): string
    {
        $waistCircumferences = array_key_exists('waist-circumference', $measurementData) ? $measurementData['waist-circumference'] : [];
        foreach ($waistCircumferences as $waistCircumference) {
            if ($ageInMonths >= 24 && $ageInMonths <= 83) {
                if ($waistCircumference < 38) {
                    return 'pSC12';
                }
                if ($waistCircumference > 92) {
                    return 'pSC13';
                }
            }
        }
        return '';
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
