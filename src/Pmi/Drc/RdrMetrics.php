<?php
namespace Pmi\Drc;

class RdrMetrics
{
    protected $rdrHelper;

    public function __construct(RdrHelper $rdrHelper)
    {
        $this->rdrHelper = $rdrHelper;
    }

    public function metrics()
    {
        $client = $this->rdrHelper->getClient();

        // TODO: not sure what the POST content format the API is expecting
        $response = $client->request('POST', 'metrics/v1/metrics', [
            'form_params' => [
                'metric' => 'PARTICIPANT_TOTAL'
            ]
        ]);
        $responseObject = json_decode($response->getBody()->getContents());
        if (!is_object($responseObject)) {
            throw new Exception\InvalidResponseException();
        }
        return $responseObject;
    }
}
