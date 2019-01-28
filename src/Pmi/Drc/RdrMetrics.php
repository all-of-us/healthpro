<?php
namespace Pmi\Drc;

class RdrMetrics
{
    protected $rdrHelper;

    public function __construct(RdrHelper $rdrHelper)
    {
        $this->rdrHelper = $rdrHelper;
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
     */
    public function metrics2($start_date, $end_date, $stratification, $centers, $enrollment_statuses, $params = [])
    {
        $client = $this->rdrHelper->getClient();

        // Additional query parameters
        $history = (isset($params['history']) && $params['history']) ? 'TRUE' : 'FALSE';

        // Convert arrays to comma separated strings
        if (is_array($centers)) {
            $centers = implode(',', $centers);
        }
        if (is_array($enrollment_statuses)) {
            $enrollment_statuses = implode(',', $enrollment_statuses);
        }

        $responseObject = [];
        foreach ($this->getDateRangeBins($start_date, $end_date) as $bucket) {
            $response = $client->request('GET', 'rdr/v1/ParticipantCountsOverTime', [
                'query' => [
                    'bucketSize' => 1,
                    'startDate' => $bucket[0],
                    'endDate' => $bucket[1],
                    'stratification' => $stratification,
                    'awardee' => $centers,
                    'enrollmentStatus' => $enrollment_statuses,
                    'history' => $history
                ]
            ]);
            $responseObject = array_merge($responseObject, json_decode($response->getBody()->getContents(), true));
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
     *
     * @return array
     */
    private function getDateRangeBins($start_date, $end_date)
    {
        $date_range_bins = [];

        $start = strtotime($start_date);
        $end = strtotime($end_date);
        $num_days_in_range = $end - $start;

        // Metrics API 2 processes no more than 100 days of data per request
        $max_days_for_metrics_api_2 = 100 * (24*60*60);

        $num_bins = ceil($num_days_in_range / $max_days_for_metrics_api_2);

        if ($num_bins == 1) {
            array_push($date_range_bins, [$start_date, $end_date]);
            return $date_range_bins;
        }

        $this_date = $start;

        for ($i = 0; $i < $num_bins; $i++) {
            $this_end_date = $this_date + $max_days_for_metrics_api_2;

            // Convert back to YYYY-MM-DD string format
            $this_date_str = date('Y-m-d', $this_date);
            $this_end_date_str = date('Y-m-d', $this_end_date);

            array_push($date_range_bins, [$this_date_str, $this_end_date_str]);
            $this_date += $max_days_for_metrics_api_2;
        }

        return $date_range_bins;
    }
}
