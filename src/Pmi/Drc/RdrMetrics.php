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

        $response = $client->request('GET', 'rdr/v1/ParticipantCountsOverTime', [
            'query' => [
                'bucketSize' => 1,
                'startDate' => $start_date,
                'endDate' => $end_date,
                'stratification' => $stratification,
                'awardee' => $centers,
                'enrollmentStatus' => $enrollment_statuses,
                'history' => $history
            ]
        ]);

        $responseObject = json_decode($response->getBody()->getContents(), true);
        return $responseObject;
    }

    public function metricsFields()
    {
        $client = $this->rdrHelper->getClient();
        $response = $client->request('GET', 'rdr/v1/MetricsFields');
        $responseObject = json_decode($response->getBody()->getContents(), true);
        return $responseObject;
    }
}
