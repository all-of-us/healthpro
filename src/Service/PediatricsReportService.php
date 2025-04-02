<?php

namespace App\Service;

use App\Entity\HeartRateAge;
use App\Entity\Incentive;
use App\Entity\Measurement;
use App\Entity\ZScores;
use App\Service\Ppsc\PpscApiService;
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
        'height-protocol-modification',
        'weight-protocol-modification',
        'head-circumference-protocol-modification',
        'waist-circumference-protocol-modification',
        'blood-pressure-protocol-modification'
    ];

    public const TOTALS_COUNTS_FIELDS = [
        'blood-pressure-systolic',
        'blood-pressure-diastolic',
        'heart-rate',
        'head-circumference',
        'irregular-heart-rate',
        'weight',
        'height',
        'waist-circumference'
    ];

    public const DEVIATION_MODIFICATION_TYPES = [
        'height-protocol-modification' => [
            '1=Height is more than measuring device range',
            '2=Hair style',
            '3= Uses a wheelchair',
            '4=Unable to stand in a straight position',
            '5=Parental refusal',
            '6=Child dissenting behavior',
            '7=Other'
        ],
        'weight-protocol-modification' => [
            '1=Weight is more than weight measuring range',
            '2=Weight is less than weight measuring range',
            '3=Can’t balance on scale',
            '4=Uses a wheelchair',
            '5=Parental refusal',
            '6=Child dissenting behavior',
            '7=Clothing not removed',
            '8=Dirty diaper',
            '9=Other'
        ],
        'waist-circumference-protocol-modification' => [
            '1 = waist is more than waist measuring device',
            '2 = clothing not removed',
            '3 = colostomy bag',
            '4 = parental refusal',
            '5 = child dissenting behavior',
            '6 = other'
        ],
        'head-circumference-protocol-modification' => [
            '1 = head is more than measuring device',
            '2 = hair style',
            '3 = parental refusal',
            '4 = child dissenting behavior',
            '5 = other'
        ],
        'blood-pressure-protocol-modification' => [
            '1 = Parental refusal',
            '2 = Child dissenting behavior',
            '3= Crying',
            '4 = Urgent/Emergent event',
            '5 = Other'
        ]
    ];

    public const MODIFICATION_TYPE_MAPPING = [
        'height-protocol-modification' => [
            'height-more-than-device-range' => '1=Height is more than measuring device range',
            'hair-style' => '2=Hair style',
            'uses-wheelchair' => '3= Uses a wheelchair',
            'unable-to-stand-straight' => '4=Unable to stand in a straight position',
            'parental-refusal' => '5=Parental refusal',
            'child-dissenting-behavior' => '6=Child dissenting behavior',
            'other' => '7=Other'
        ],
        'weight-protocol-modification' => [
            'weight-more-than-range' => '1=Weight is more than weight measuring range',
            'weight-less-than-range' => '2=Weight is less than weight measuring range',
            'cant-balance-on-scale' => '3=Can’t balance on scale',
            'uses-wheelchair' => '4=Uses a wheelchair',
            'parental-refusal' => '5=Parental refusal',
            'child-dissenting-behavior' => '6=Child dissenting behavior',
            'clothing-not-removed' => '7=Clothing not removed',
            'dirty-diaper' => '8=Dirty diaper',
            'other' => '9=Other'
        ],
        'waist-circumference-protocol-modification' => [
            'waist-more-than-device-range' => '1 = waist is more than waist measuring device',
            'clothing-not-removed' => '2 = clothing not removed',
            'colostomy-bag' => '3 = colostomy bag',
            'parental-refusal' => '4 = parental refusal',
            'child-dissenting-behavior' => '5 = child dissenting behavior',
            'other' => '6 = other'
        ],
        'head-circumference-protocol-modification' => [
            'head-more-than-device-range' => '1 = head is more than measuring device',
            'hair-style' => '2 = hair style',
            'parental-refusal' => '3 = parental refusal',
            'child-dissenting-behavior' => '4 = child dissenting behavior',
            'other' => '5 = other'
        ],
        'blood-pressure-protocol-modification' => [
            'parental-refusal' => '1 = Parental refusal',
            'child-dissenting-behavior' => '2 = Child dissenting behavior',
            'crying' => '3= Crying',
            'urgent-emergent-event' => '4 = Urgent/Emergent event',
            'other' => '5 = Other'
        ]
    ];

    private const MEASUREMENTS_TOTAL_CSV_HEADERS = [
        '',
        'Unique PPTs w/ Complete Measurements', 'Unique PPTs w/ Complete Measurements', 'Unique PPTs w/ Complete Measurements',
        'Unique PPTs w/ any Measurements', 'Unique PPTs w/ any Measurements', 'Unique PPTs w/ any Measurements',
        'Unique PPTs with third measurement taken', 'Unique PPTs with third measurement taken', 'Unique PPTs with third measurement taken'
    ];

    private const MEASUREMENTS_TOTAL_CSV_SUBHEADERS = [
        'Measurement Type',
        '<1 year', '1-3 years', '4-6 years',
        '<1 year', '1-3 years', '4-6 years',
        '<1 year', '1-3 years', '4-6 years'
    ];

    private const ALERTS_CSV_HEADERS = [
        'alert',
        '<1 year',
        '1-3 years',
        '4-6 years',
    ];

    protected EntityManagerInterface $em;
    protected GcsBucketService $gcsBucketService;
    protected EnvironmentService $env;
    protected MeasurementService $measurementService;
    protected PpscApiService $ppscApiService;
    protected array $zScores;

    public function __construct(
        EntityManagerInterface $em,
        GcsBucketService $gcsBucketService,
        EnvironmentService $env,
        MeasurementService $measurementService,
        PpscApiService $ppscApiService
    ) {
        $this->em = $em;
        $this->measurementService = $measurementService;
        $this->gcsBucketService = $gcsBucketService;
        $this->ppscApiService = $ppscApiService;
        $this->env = $env;
        $this->zScores = $this->em->getRepository(ZScores::class)->getChartsData();
    }

    public function generateIncentiveReport(\DateTime $startDate, \DateTime $endDate): void
    {
        $incentives = $this->em->getRepository(Incentive::class)->getPediatricIncentivesForReport($startDate, $endDate);
        $csvData = $this->getIncentiveReportCSVData($incentives);
        $this->generateCSVReport($csvData, 'Incentive_Report-' . date('Ymd-His') . '.csv');
    }

    public function getIncentiveReportCSVData(array $incentives)
    {
        $csvData = [];
        $csvData[] = ['Participant ID', 'Date Created', 'Site', 'Recipient', 'Date of Service', 'Incentive Occurrence',
            'Incentive Type', 'Gift Card Type', 'Appreciation Item Type', 'Appreciation Item Count', 'Incentive Amount', 'Declined'];
        foreach ($incentives as $incentive) {
            $csvData[] = [$incentive['participantId'], $incentive['createdTs']->format('Y-m-d H:i:s'), $incentive['site'],
                $incentive['Recipient'], $incentive['incentiveDateGiven']->format('Y-m-d'), $incentive['incentiveOccurrence'],
                $incentive['incentiveType'], $incentive['giftCardType'], $incentive['typeOfItem'], $incentive['numberOfItems'],
                $incentive['incentiveAmount'], $incentive['declined']];
        }
        return $csvData;
    }

    public function generateDeviationReport(): void
    {
        $evaluationsTotalData = [];
        $evaluationsTotalData[] = ['Report Date', date('m/d/Y')];
        $evaluationsTotalData[] = ['']; // Empty row for spacing

        foreach (self::DEVIATION_FIELDS as $field) {
            $headerRow = ['Protocol Deviations for ' . $this->formatFieldName($field)];
            $evaluationsTotalData[] = $headerRow;

            // Prepare storage for all modifications found across age ranges
            $modificationCounts = [];
            $ageHeaders = ['Modification Type'];

            // Prepopulate modification counts with zero values
            foreach (self::DEVIATION_MODIFICATION_TYPES[$field] as $modType) {
                $modificationCounts[$modType] = array_fill_keys(array_keys(self::DEVIATION_AGE_RANGES), 0);
            }

            foreach (self::DEVIATION_AGE_RANGES as $ageLabel => $ageRange) {
                $ageHeaders[] = "Count ($ageLabel years)";

                $evaluations = $this->em->getRepository(Measurement::class)
                    ->getProtocolModificationCount($field, $ageRange[0], $ageRange[1]);

                if (!empty($evaluations)) {
                    foreach ($evaluations as $evaluation) {
                        $modType = $this->formatModificationType($evaluation['modificationType'], $field);
                        if (isset($modificationCounts[$modType])) {
                            $modificationCounts[$modType][$ageLabel] = $evaluation['count'];
                        }
                    }
                }
            }

            // Append Age Range Headers
            $evaluationsTotalData[] = $ageHeaders;

            // Add collected data row-wise
            foreach ($modificationCounts as $modType => $counts) {
                $row = [$modType];
                foreach (array_keys(self::DEVIATION_AGE_RANGES) as $ageLabel) {
                    $row[] = $counts[$ageLabel] ?? 0; // Retain prepopulated zero values
                }
                $evaluationsTotalData[] = $row;
            }

            $evaluationsTotalData[] = ['']; // Empty row for spacing
        }

        $this->generateCSVReport($evaluationsTotalData, 'Protocol_Deviations_Report-' . date('Ymd-His') . '.csv');
    }

    public function generateActiveAlertReport(): void
    {
        $csvData = [];
        $heartRateAgeCharts = $this->em->getRepository(HeartRateAge::class)->getChartsData();
        $alertsData = [];
        foreach (self::DEVIATION_AGE_RANGES as $ageText => $ageRange) {
            $alertsData[$ageText] = $this->buildBlankAlertArray();
            $measurements = $this->em->getRepository(Measurement::class)
                ->getActiveAlertsReportData(
                    $ageRange[0],
                    $ageRange[1]
                );
            /**
             * @var Measurement $measurement
             */
            foreach ($measurements as $measurement) {
                $measurementData = json_decode($measurement->getData(), true);
                $sexAtBirth = $measurement->getSexAtBirth();
                if (empty($sexAtBirth)) {
                    continue;
                }
                $growthChartsByAge = $measurement->getGrowthChartsByAge((int) $measurement->getAgeInMonths());
                $headCircumferenceChart = $growthChartsByAge['headCircumferenceForAgeCharts'] ? $this->em->getRepository($growthChartsByAge['headCircumferenceForAgeCharts'])->getChartsData($sexAtBirth) : []; // @phpstan-ignore-line
                $weightChart = $growthChartsByAge['weightForAgeCharts'] ? $this->em->getRepository($growthChartsByAge['weightForAgeCharts'])->getChartsData($sexAtBirth) : []; // @phpstan-ignore-line
                $weightForLengthChart = $growthChartsByAge['weightForLengthCharts'] ? $this->em->getRepository($growthChartsByAge['weightForLengthCharts'])->getChartsData($sexAtBirth) : []; // @phpstan-ignore-line
                $bmiChart = $growthChartsByAge['bmiForAgeCharts'] ? $this->em->getRepository($growthChartsByAge['bmiForAgeCharts'])->getChartsData($sexAtBirth) : []; // @phpstan-ignore-line
                $heightForAgeChart = $growthChartsByAge['heightForAgeCharts'] ? $this->em->getRepository($growthChartsByAge['heightForAgeCharts'])->getChartsData($sexAtBirth) : [];
                $bloodPressureSystolicHeightChart = $growthChartsByAge['bloodPressureSystolicHeightChart'] ? $this->em->getRepository($growthChartsByAge['bloodPressureSystolicHeightChart'])->getChartsData($sexAtBirth) : [];
                $bloodPressureSystolicAlert = $this->getBloodPressureSystolicAlert($measurement, $measurementData, $measurement->getAgeInMonths(), $sexAtBirth, $heightForAgeChart, $bloodPressureSystolicHeightChart);
                $bloodPressureDiastolicAlert = $this->getBloodPressureDiastolicAlert($measurement, $measurementData, $measurement->getAgeInMonths(), $sexAtBirth, $heightForAgeChart, $bloodPressureSystolicHeightChart);
                $heartRateAlert = $this->getHeartRateAlert($measurementData, $measurement->getAgeInMonths(), $heartRateAgeCharts);
                $headCircumferenceAlert = $this->getHeadCircumferenceAlert($measurement, $measurementData, $measurement->getAgeInMonths(), $sexAtBirth, $headCircumferenceChart);
                $irregularHeartRhythmAlert = $this->getIrregularHeartRhythmAlert($measurementData);
                $weightAlert = $this->getWeightAlert($measurement, $measurementData, $measurement->getAgeInMonths(), $weightChart, $sexAtBirth);
                $weightForLengthAlert = $this->getWeightForLengthAlert($measurement, $measurementData, $weightForLengthChart, $sexAtBirth);
                $bmiAlert = $this->getBMIAlert($measurement, $measurementData, $measurement->getAgeInMonths(), $bmiChart, $sexAtBirth);
                $heightAlert = $this->getHeightAlert($measurementData, $measurement->getAgeInMonths());
                $waistAlert = $this->getWaistAlert($measurementData, $measurement->getAgeInMonths());
                if (!empty($heartRateAlert)) {
                    $alertsData[$ageText]['Heart Rate'][$heartRateAlert]++;
                }
                if (!empty($headCircumferenceAlert)) {
                    $alertsData[$ageText]['Head Circumference'][$headCircumferenceAlert]++;
                }
                if (!empty($irregularHeartRhythmAlert)) {
                    $alertsData[$ageText]['Irregular Heart Rhythm'][$irregularHeartRhythmAlert]++;
                }
                if (!empty($weightAlert)) {
                    $alertsData[$ageText]['Weight'][$weightAlert]++;
                }
                if (!empty($weightForLengthAlert)) {
                    $alertsData[$ageText]['Weight for Length'][$weightForLengthAlert]++;
                }
                if (!empty($bmiAlert)) {
                    $alertsData[$ageText]['BMI'][$bmiAlert]++;
                }
                if (!empty($heightAlert)) {
                    $alertsData[$ageText]['Height/Length'][$heightAlert]++;
                }
                if (!empty($waistAlert)) {
                    $alertsData[$ageText]['Waist Circumference'][$waistAlert]++;
                }
                if (!empty($bloodPressureSystolicAlert)) {
                    $alertsData[$ageText]['Blood Pressure Systolic'][$bloodPressureSystolicAlert]++;
                }
                if (!empty($bloodPressureDiastolicAlert)) {
                    $alertsData[$ageText]['Blood Pressure Diastolic'][$bloodPressureDiastolicAlert]++;
                }
            }
        }
        $csvHeaders = [
            ['Report Date', date('m/d/Y')],
            self::ALERTS_CSV_HEADERS
        ];

        $tempRows = [];
        $ageRanges = array_keys(self::DEVIATION_AGE_RANGES);

        // Collect all unique alert codes
        foreach ($ageRanges as $ageRange) {
            foreach ($alertsData[$ageRange] as $alertData) {
                foreach ($alertData as $alert => $alertCount) {
                    $tempRows[$alert][$ageRange] = $alertCount;
                }
            }
        }
        foreach ($tempRows as $alert => $alertCounts) {
            $csvData[] = array_merge([$alert], array_values($alertCounts));
        }

        // Sort the entries by the first index using natural order comparison
        usort($csvData, function ($a, $b) {
            return strnatcmp($a[0], $b[0]);
        });

        $csvData = array_merge($csvHeaders, $csvData);
        $this->generateCSVReport($csvData, 'Active_Alerts_Report-' . date('Ymd-His') . '.csv');
    }

    public function generateMeasurementTotalsReport(): void
    {
        // Define CSV headers
        $csvData[] = ['Report Date', date('m/d/Y')];
        $csvData[] = self::MEASUREMENTS_TOTAL_CSV_HEADERS;
        $csvData[] = self::MEASUREMENTS_TOTAL_CSV_SUBHEADERS;

        foreach (self::TOTALS_COUNTS_FIELDS as $field) {
            $tempRow = [$field];

            // Fetch all three categories of data at once per age range
            foreach (self::DEVIATION_AGE_RANGES as $ageRange) {
                $completeMeasurements = $this->em->getRepository(Measurement::class)
                    ->getCompleteMeasurementsForPediatrictotalsReport($field, $ageRange[0], $ageRange[1]);

                $tempRow[] = $completeMeasurements[0]['participant_count'] ?? 0;
            }

            foreach (self::DEVIATION_AGE_RANGES as $ageRange) {
                $anyMeasurements = $this->em->getRepository(Measurement::class)
                    ->getAnyMeasurementsForPediatrictotalsReport($field, $ageRange[0], $ageRange[1]);

                $tempRow[] = $anyMeasurements[0]['participant_count'] ?? 0;
            }

            foreach (self::DEVIATION_AGE_RANGES as $ageRange) {
                $thirdMeasurements = $this->em->getRepository(Measurement::class)
                    ->getThridMeasurementsForPediatrictotalsReport($field, $ageRange[0], $ageRange[1]);

                $tempRow[] = $thirdMeasurements[0]['participant_count'] ?? 0;
            }

            $csvData[] = $tempRow;
        }

        $this->generateCSVReport($csvData, 'Measurements_Report-' . date('Ymd-His') . '.csv');
    }

    public function getBloodPressureSystolicAlert(
        $measurement,
        array $measurementData,
        int $ageInMonths,
        int $sexAtBirth,
        array $heightForAgeChart,
        array $bloodPressureSystolicHeightChart
    ): string {
        return $this->getBloodPressureAlert(
            $measurement,
            $measurementData,
            $ageInMonths,
            $sexAtBirth,
            $heightForAgeChart,
            $bloodPressureSystolicHeightChart,
            'blood-pressure-systolic',
            140,
            'pME1c',
            'pME1',
            'pME1b',
            'pME1d',
            'pSC15',
            'pSC16',
            88,
            126
        );
    }

    public function getBloodPressureDiastolicAlert(
        $measurement,
        array $measurementData,
        int $ageInMonths,
        int $sexAtBirth,
        array $heightForAgeChart,
        array $bloodPressureSystolicHeightChart
    ): string {
        return $this->getBloodPressureAlert(
            $measurement,
            $measurementData,
            $ageInMonths,
            $sexAtBirth,
            $heightForAgeChart,
            $bloodPressureSystolicHeightChart,
            'blood-pressure-diastolic',
            90,
            'pME2c',
            'pME2',
            'pME2b',
            'pME2d',
            'pSC17',
            'pSC18',
            45,
            86
        );
    }

    private function formatFieldName(string $field): string
    {
        return ucwords(str_replace('-', ' ', str_replace('-protocol-modification', '', $field)));
    }

    private function formatModificationType(string $modType, string $field): string
    {
        return self::MODIFICATION_TYPE_MAPPING[$field][$modType] ?? ucfirst(str_replace('-', ' ', $modType));
    }

    private function buildBlankAlertArray(): array
    {
        return [
            'Blood Pressure Systolic' => [
                'pME1' => 0,
                'pME1b' => 0,
                'pME1c' => 0,
                'pME1d' => 0,
                'pSC15' => 0,
                'pSC16' => 0
            ],
            'Blood Pressure Diastolic' => [
                'pME2' => 0,
                'pME2b' => 0,
                'pME2c' => 0,
                'pME2d' => 0,
                'pSC17' => 0,
                'pSC18' => 0
            ],
            'Heart Rate' => [
                'pME5' => 0,
                'pME5b' => 0,
                'pME5c' => 0,
                'pME5d' => 0,
                'pME6' => 0,
                'pME6a' => 0,
                'pME6b' => 0,
                'pME6c' => 0,
                'pSC19' => 0,
                'pSC20' => 0,
                'pSC21' => 0,
                'pSC22' => 0
            ],
            'Head Circumference' => [
                'pME7a' => 0,
                'pME7b' => 0,
                'pSC10' => 0,
                'pSC11' => 0
            ],
            'Irregular Heart Rhythm' => [
                'pME8' => 0
            ],
            'Weight' => [
                'pME9' => 0,
                'pME10' => 0,
                'pSC6' => 0,
                'pSC7' => 0,
                'pSC8' => 0,
                'pSC9' => 0
            ],
            'Weight for Length' => [
                'pME11' => 0,
                'pME12' => 0
            ],
            'BMI' => [
                'pME13' => 0,
                'pME14' => 0,
                'pSC25' => 0,
                'pSC26' => 0
            ],
            'Height/Length' => [
                'pSC1' => 0,
                'pSC2' => 0,
                'pSC3' => 0,
                'pSC4' => 0,
                'pSC5' => 0,
                'pSC14' => 0
            ],
            'Waist Circumference' => [
                'pSC12' => 0,
                'pSC13' => 0
            ]
        ];
    }

    private function getLMSValues(array $heightForAgeChart, int $ageInMonths, int $sexAtBirth): array
    {
        foreach ($heightForAgeChart as $item) {
            if ($item['sex'] == $sexAtBirth && floor($item['month']) == $ageInMonths) {
                return ['L' => $item['L'], 'M' => $item['M'], 'S' => $item['S']];
            }
        }
        return [];
    }

    private function getBloodPressureAlert(
        $measurement,
        array $measurementData,
        int $ageInMonths,
        int $sexAtBirth,
        array $heightForAgeChart,
        array $bloodPressureChart,
        string $bpKey,
        int $optionalThreshold1,
        string $alert1,
        string $alert2,
        string $alert3,
        string $alert4,
        string $alert5,
        string $alert6,
        int $sanityCheckWarning1,
        int $sanityCheckWarning2
    ): string {
        if (!isset($measurementData[$bpKey])) {
            return '';
        }

        if ($ageInMonths < 13) {
            $heights = $measurementData['height'];
            $heightMean = $measurement->calculateMeanFromValues($heights);
            $lmsValues = $this->getLMSValues($heightForAgeChart, $ageInMonths, $sexAtBirth);

            if (empty($lmsValues)) {
                return '';
            }

            $zScore = $measurement->calculateZScore($heightMean, $lmsValues['L'], $lmsValues['M'], $lmsValues['S']);
            $heightPercentile = $measurement->calculatePercentile($zScore, $this->zScores);
            $heightPercentileField = 'heightPer' . ($heightPercentile ? $this->roundDownToNearestPercentile($heightPercentile) : '5');

            $maxValue1 = $this->getMaxValueForPercentile(12, $sexAtBirth, $heightPercentileField, $bloodPressureChart, $ageInMonths, $optionalThreshold1);
            $maxValue2 = $this->getMaxValueForPercentile(30, $sexAtBirth, $heightPercentileField, $bloodPressureChart, $ageInMonths);

            $count1 = 0;
            $count2 = 0;

            foreach ($measurementData[$bpKey] as $bp) {
                if (!empty($bp)) {
                    if ($bp >= $maxValue1) {
                        $count1++;
                    }
                    if ($bp >= $maxValue2) {
                        $count2++;
                    }
                }
            }

            if ($count1 === 1) {
                return $alert1;
            }
            if ($count1 >= 2) {
                return $alert2;
            }
            if ($count2 === 1) {
                return $alert3;
            }
            if ($count2 >= 2) {
                return $alert4;
            }
        }

        if ($ageInMonths >= 36 && $ageInMonths <= 83) {
            foreach ($measurementData[$bpKey] as $bp) {
                if (!empty($bp)) {
                    if ($bp < $sanityCheckWarning1) {
                        return $alert5;
                    }
                    if ($bp > $sanityCheckWarning2) {
                        return $alert6;
                    }
                }
            }
        }

        return '';
    }

    private function roundDownToNearestPercentile($percentile): int
    {
        $percentiles = [5, 10, 25, 50, 75, 90, 95];
        $result = $percentiles[0];

        foreach ($percentiles as $value) {
            if ($percentile >= $value) {
                $result = $value;
            } else {
                break;
            }
        }

        return $result;
    }

    private function getMaxValueForPercentile($addValue, $sex, $heightPercentileField, $bpHeightPercentileCharts, $ageInMonths, $defaultMaxValue = null)
    {
        $maxValue = null;
        $ageInYears = $this->getAgeInYears($ageInMonths);
        foreach ($bpHeightPercentileCharts as $bpHeightPercentile) {
            if (
                $bpHeightPercentile['sex'] === $sex &&
                $ageInYears === $bpHeightPercentile['ageYear'] &&
                $bpHeightPercentile['bpCentile'] === 95
            ) {
                $maxValue = $bpHeightPercentile[$heightPercentileField] + $addValue;
                break;
            }
        }

        if ($defaultMaxValue && $maxValue > $defaultMaxValue) {
            $maxValue = $defaultMaxValue;
        }

        return $maxValue;
    }

    private function getAgeInYears(?int $ageInMonths): ?int
    {
        if ($ageInMonths === null) {
            return null;
        }

        return (int) floor($ageInMonths / 12);
    }

    private function getHeartRateAlert(array $measurementData, float $ageInMonths, array $heartRateAgeCharts): string
    {
        if (!array_key_exists('heart-rate', $measurementData)) {
            return '';
        }
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
                if (!empty($heartRate)) {
                    if ($heartRate > 175) {
                        $heartRateOver175++;
                    }
                    if ($heartRate > 200) {
                        $heartRateOver200++;
                    }
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
                if (!empty($heartRate)) {
                    if ($heartRate > 175) {
                        $heartRateOver175++;
                    }
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
            if (!empty($heartRate)) {
                if ($heartRate < $heartCentiles['centile1']) {
                    $centile1Count++;
                }
                if ($heartRate > $heartCentiles['centile99']) {
                    $centile99Count++;
                }
            }
        }
        switch ($centile99Count) {
            case 2:
                return 'pME6';
            case 1:
                return 'pME6a';
        }
        switch ($centile1Count) {
            case 2:
                return 'pME6b';
            case 1:
                return 'pME6c';
        }
        if ($ageInMonths <= 35) {
            $heartRateLessThan85 = 0;
            $heartRateOver205 = 0;
            foreach ($heartRates as $heartRate) {
                if (!empty($heartRate)) {
                    if ($heartRate < 85) {
                        $heartRateLessThan85++;
                    }
                    if ($heartRate > 205) {
                        $heartRateOver205++;
                    }
                }
            }
            if ($heartRateLessThan85 >= 1) {
                return 'pSC19';
            }
            if ($heartRateOver205 >= 1) {
                return 'pSC20';
            }
        }
        if ($ageInMonths >= 36 && $ageInMonths <= 83) {
            $heartRateLessThan60 = 0;
            $heartRateOver140 = 0;
            foreach ($heartRates as $heartRate) {
                if (!empty($heartRate)) {
                    if ($heartRate < 60) {
                        $heartRateLessThan60++;
                    }
                    if ($heartRate > 140) {
                        $heartRateOver140++;
                    }
                }
            }
            if ($heartRateLessThan60 >= 1) {
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
        if (!array_key_exists('head-circumference', $measurementData)) {
            return '';
        }
        $headCircumferences = $measurementData['head-circumference'];
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

        if ($ageInMonths < 36) {
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
            foreach ($headCircumferences as $headCircumference) {
                if (!empty($headCircumference)) {
                    if ($headCircumference < 29) {
                        return 'pSC10';
                    }
                    if ($headCircumference > 55) {
                        return 'pSC11';
                    }
                }
            }
        }
        return '';
    }
    private function getIrregularHeartRhythmAlert(array $measurementData): string
    {
        if (!array_key_exists('irregular-heart-rate', $measurementData)) {
            return '';
        }
        foreach ($measurementData['irregular-heart-rate'] as $irregularHeartRate) {
            if ($irregularHeartRate) {
                return 'pME8';
            }
        }
        return '';
    }
    private function getWeightAlert(Measurement $measurement, array $measurementData, float $ageInMonths, array $growthChartsByAge, int $sex): string
    {
        if (!array_key_exists('weight', $measurementData)) {
            return '';
        }
        $weights = $measurementData['weight'];
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
                if (!empty($weight)) {
                    if ($weight < 1) {
                        return 'pSC6';
                    }
                    if ($weight > 18) {
                        return 'pSC7';
                    }
                }
            }
        } elseif ($ageInMonths >= 36 && $ageInMonths <= 83) {
            foreach ($weights as $weight) {
                if (!empty($weight)) {
                    if ($weight < 7) {
                        return 'pSC8';
                    }
                    if ($weight > 35) {
                        return 'pSC9';
                    }
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
                    if ($percentile !== null && $percentile < 2.3) {
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
            if (!empty($height)) {
                if ($height > 228) {
                    return 'pSC14';
                }

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
        }
        return '';
    }
    private function getWaistAlert(array $measurementData, float $ageInMonths): string
    {
        $waistCircumferences = array_key_exists('waist-circumference', $measurementData) ? $measurementData['waist-circumference'] : [];
        foreach ($waistCircumferences as $waistCircumference) {
            if (!empty($waistCircumference)) {
                if ($ageInMonths >= 24 && $ageInMonths <= 83) {
                    if ($waistCircumference < 38) {
                        return 'pSC12';
                    }
                    if ($waistCircumference > 92) {
                        return 'pSC13';
                    }
                }
            }
        }
        return '';
    }

    private function generateCSVReport(array $csvData, string $csvTitle)
    {
        // Create a temporary stream to hold the CSV data
        $tempStream = fopen('php://temp', 'w');

        // Add UTF-8 BOM to prevent character encoding issues (especially for Excel)
        fprintf($tempStream, chr(0xEF) . chr(0xBB) . chr(0xBF));

        foreach ($csvData as $row) {
            fputcsv($tempStream, $row);
        }
        $bucketName = $this->env->isProd() ? self::BUCKET_NAME_PROD : self::BUCKET_NAME_STABLE;
        $this->gcsBucketService->uploadFile($bucketName, $tempStream, $csvTitle);
    }
}
