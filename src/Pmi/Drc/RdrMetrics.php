<?php
namespace Pmi\Drc;

class RdrMetrics
{
    protected $rdrHelper;
    protected $cache;

    /**
     * @var int
     *
     * Size of date range batches if 'history' flag is true; Maximum of 600, but
     * reduced so that the payload size < 1 MiB for caching
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
     */
    public function __construct(RdrHelper $rdrHelper)
    {
        $this->rdrHelper = $rdrHelper;
        $this->cache = $rdrHelper->getCache();
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
    public function metrics($start_date, $end_date, $stratification, $centers, $enrollment_statuses, $params = [])
    {
        $client = $this->rdrHelper->getClient();

        // Handle history flag
        $history = false;
        $version = null;
        $batch = self::BATCH_DAYS_NO_HISTORY;
        if (isset($params['history']) && $params['history']) {
            $history = true;
            $batch = self::BATCH_DAYS_HISTORY;
        }
        if (isset($params['version']) && $params['version']) {
            $version = $params['version'];
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
                'history' => $history ? 'TRUE' : 'FALSE',
                'version' => $version ? $version : null
            ];

            // Generate a cache key
            $cacheKey = 'metrics_api_2_' . md5(json_encode($query));

            $metrics = null;
            if ($this->cache) {
                // Check cache
                $cacheItem = $this->cache->getItem($cacheKey);
                if ($cacheItem->isHit()) {
                    $metrics = $cacheItem->get();
                }
            }
            if (!$metrics) {
                $request_options = [
                    'query' => $query
                ];
                $response = $client->request('GET', 'rdr/v1/ParticipantCountsOverTime', $request_options);
                $metrics = json_decode($response->getBody()->getContents(), true);

                // Store results in cache if enabled
                if ($this->cache && $metrics) {
                    $cacheItem->set($metrics);
                    $cacheItem->expiresAfter(900); // 15 minutes
                    $this->cache->save($cacheItem);
                }
            }

            // Merge results
            $responseObject = array_merge($responseObject, $metrics);
        }

        return $responseObject;
    }


    /**
     * EHR Metrics
     *
     * @param string $mode
     * @param string $end_date YYYY-MM-DD
     * @param array $organizations
     *
     * @return array
     */
    public function ehrMetrics($mode, $end_date, $organizations)
    {
        $client = $this->rdrHelper->getClient();

        // Convert arrays to comma separated strings
        if (is_array($organizations)) {
            $organizations = implode(',', $organizations);
        }

        $query = [
            'end_date' => $end_date,
        ];
        if ($organizations) {
            $query['organization'] = $organizations;
        }

        // Generate a cache key
        $cacheKey = 'metrics_ehr_api_' . md5(json_encode($query));

        $metrics = null;
        if ($this->cache) {
            // Check cache
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                $metrics = $cacheItem->get();
            }
        }
        if (!$metrics) {
            $request_options = [
                'query' => $query
            ];
            $endpoint = 'rdr/v1/MetricsEHR';
            $response = $client->request('GET', $endpoint, $request_options);
            $metrics = json_decode($response->getBody()->getContents(), true);

            // Store results in cache if enabled
            if ($this->cache && $metrics) {
                $cacheItem->set($metrics);
                $cacheItem->expiresAfter(900); // 15 minutes
                $this->cache->save($cacheItem);
            }
        }

        // Allowing the consolidated call to behave as if two different endpoints were used
        switch ($mode) {
            case 'Organizations':
                return $metrics['organization_metrics'];
                break;
            case 'ParticipantsOverTime':
                return $metrics['metrics'];
                break;
            default:
                return $metrics;
                break;
        }
    }

    /**
     * Metric Fields
     *
     * @return object
     */
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
        $startDate = new \DateTime($start_date . '00:00:00');
        $endDate = new \DateTime($end_date . '23:59:59');

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
