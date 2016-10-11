<?php
namespace Pmi\Drc;

class RdrMetrics
{
    protected $rdrHelper;

    public function __construct(RdrHelper $rdrHelper)
    {
        $this->rdrHelper = $rdrHelper;
    }

    public function metrics($metric, $bucket)
    {
        $client = $this->rdrHelper->getClient();
        $response = $client->request('POST', 'metrics/v1/metrics', [
            'json' => [
                'metric' => $metric,
                'bucket_by' => $bucket
            ]
        ]);
        $responseObject = json_decode($response->getBody()->getContents());
        if (!is_object($responseObject)) {
            // Response could be double-encoded. Try double decoding.
            $responseObject = json_decode($responseObject);
            if (!is_object($responseObject)) {
                throw new Exception\InvalidResponseException();
            }
        }
        return $responseObject;
    }
}
