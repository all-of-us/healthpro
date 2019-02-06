<?php
namespace Pmi\Drc;

class RdrMetrics
{
    protected $rdrHelper;
    protected $memcache;

    /**
     * @var int
     *
     * Size of date range batches if 'history' flag is true; Maximum of 600, but
     * reduced so that the payload size < 1 MiB for Memcache
     */
    const BATCH_DAYS_HISTORY = 200;

    /**
     * @var int
     *
     * Size of date range batches if 'history' flag is false; Triggers an API
     * error if exceeded.
     */
    const BATCH_DAYS_NO_HISTORY = 100;

    /**
     * Constructor
     *
     * @param RdrHelper $rdrHelper
     * @param Memcache|false $memcache
     */
    public function __construct(RdrHelper $rdrHelper, $memcache = false)
    {
        $this->rdrHelper = $rdrHelper;
        $this->memcache = $memcache;
    }

    /**
     * @deprecated 2018-10-01 Use ::metrics2 instead.
     */
    public function metrics($start_date, $end_date)
    {
        $client = $this->rdrHelper->getClient();
        $response = $client->request('POST', 'rdr/v1/Metrics', [
            'json' => [
                'start_date' => $start_date,
                'end_date' => $end_date
            ]
        ]);
        $responseObject = json_decode($response->getBody()->getContents(), true);
        return $responseObject;
    }

    /**
     * Metrics 2 API (MAPI2)
     *
     * @param string $start_date YYYY-MM-DD
     * @param string $end_date YYYY-MM-DD
     * @param string $stratification
     * @param array $centers
     * @param array $enrollment_statuses
     * @param array $params
     *
     * @return array
     */
    public function metrics2($start_date, $end_date, $stratification, $centers, $enrollment_statuses, $params = [])
    {
        $client = $this->rdrHelper->getClient();

        // Handle history flag
        $history = false;
        $batch = self::BATCH_DAYS_NO_HISTORY;
        if (isset($params['history']) && $params['history']) {
            $history = true;
            $batch = self::BATCH_DAYS_HISTORY;
        }

        // Convert arrays to comma separated strings
        if (is_array($centers)) {
            $centers = implode(',', $centers);
        }
        if (is_array($enrollment_statuses)) {
            $enrollment_statuses = implode(',', $enrollment_statuses);
        }

        $responseObject = [];
        foreach ($this->getDateRangeBins($start_date, $end_date, $batch) as $bucket) {
            $query = [
                'bucketSize' => 1,
                'startDate' => $bucket[0],
                'endDate' => $bucket[1],
                'stratification' => $stratification,
                'awardee' => $centers,
                'enrollmentStatus' => $enrollment_statuses,
                'history' => $history ? 'TRUE' : 'FALSE'
            ];

            // Generate a cache key
            $memcacheKey = 'metrics_api_2_' . md5(json_encode($query));

            // If not found in Memcache, make request
            if (!$this->memcache || !$metrics = $this->memcache->get($memcacheKey)) {
                $request_options = [
                    'query' => $query
                ];
                $response = $client->request('GET', 'rdr/v1/ParticipantCountsOverTime', $request_options);
                $metrics = json_decode($response->getBody()->getContents(), true);

                // Store results in Memcache if enabled
                if ($this->memcache) {
                    $this->memcache->set($memcacheKey, $metrics, 0, 900); // 900 s = 15 min
                }
            }

            // Merge results
            $responseObject = array_merge($responseObject, $metrics);
        }

        return $responseObject;
    }

    public function metricsFields()
    {
        $client = $this->rdrHelper->getClient();
        $response = $client->request('GET', 'rdr/v1/MetricsFields');
        $responseObject = json_decode($response->getBody()->getContents(), true);
        return $responseObject;
    }

    /* Private Methods */

    /**
     * Get Date Range Bins
     *
     * Break up large date ranges segmented by maximum Metrics API 2 range
     *
     * @param string $start_date
     * @param string $end_date
     * @param int $batch
     *
     * @return array
     */
    private function getDateRangeBins($start_date, $end_date, $batch)
    {
        $dateRangeBins = [];
        $startDate = new \DateTime($start_date);
        $endDate = new \DateTime($end_date);

        $interval = new \DateInterval(sprintf('P%dD', $batch));
        $period = new \DatePeriod($startDate, $interval, $endDate);

        // Loop through calculated intervals
        foreach ($period as $i => $intervalDate) {
            $intervalStartDate = clone $intervalDate;
            // Add one day for subsequent interval starts
            if ($i > 0) {
                $intervalStartDate->add(new \DateInterval('P1D'));
            }
            // Set thet end date one period after the interval start
            $intervalEndDate = clone $intervalDate;
            $intervalEndDate->add($interval);

            // Truncate the bin if it exceeds the overall end date
            if ($intervalEndDate > $endDate) {
                $intervalEndDate = $endDate;
            }
            $dateRangeBins[] = [$intervalStartDate->format('Y-m-d'), $intervalEndDate->format('Y-m-d')];
        }
        return $dateRangeBins;
    }
}
