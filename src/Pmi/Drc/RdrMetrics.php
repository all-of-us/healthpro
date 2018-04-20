<?php
namespace Pmi\Drc;

class RdrMetrics
{
    protected $rdrHelper;

    public function __construct(RdrHelper $rdrHelper)
    {
        $this->rdrHelper = $rdrHelper;
    }

    public function metrics($start_date, $end_date)
    {
        $client = $this->rdrHelper->getClient();
        $response = $client->request('POST', 'rdr/v1/Metrics', [
            'json' => [
                'start_date' => $start_date,
                'end_date' => $end_date
            ]
        ]);
        $responseObject = json_decode($response->getBody()->getContents(), True);
        return $responseObject;
    }

    public function metrics2($start_date, $end_date, $stratification, $centers, $enrollment_statuses)
    {
        $client = $this->rdrHelper->getClient();
        $queryString =
            '?bucketSize=1' .
             '&startDate=' . $start_date .
            '&endDate=' . $end_date .
            '&stratification=' . $stratification .
            '&awardee' . $centers .
            '&enrollmentStatus=' . $enrollment_statuses;

        $response = $client->request('GET', 'rdr/v1/ParticipantCountsOverTime' . $queryString);
        $responseObject = json_decode($response->getBody()->getContents(), True);
        return $responseObject;
    }

    public function metricsFields()
    {
        $client = $this->rdrHelper->getClient();
        $response = $client->request('GET', 'rdr/v1/MetricsFields');
        $responseObject = json_decode($response->getBody()->getContents(), True);
        return $responseObject;
    }
}
