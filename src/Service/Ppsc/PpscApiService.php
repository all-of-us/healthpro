<?php

namespace App\Service\Ppsc;

use App\HttpClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PpscApiService
{
    protected $client;
    protected $endpoint = 'http://nih-norc-participant-prc-api.usg-w1.gov.cloudhub.io/dev/prc/v1/api/';

    public function __construct(ParameterBagInterface $params)
    {
        // Load endpoint from configuration
        if ($params->has('ppsc_endpoint')) {
            $this->endpoint = $params->get('norc_endpoint');
        }
        $this->client = new HttpClient(['cookies' => true]);
    }

    public function get($path, $params = [])
    {
        return $this->client->request('GET', $this->endpoint . $path, ['query' => $params]);
    }

    public function post($path, $body, $params = [])
    {
        $params['json'] = $body;
        return $this->client->request('POST', $this->endpoint . $path, $params);
    }

    public function put($path, $body, $params = [])
    {
        $params['json'] = $body;
        return $this->client->request('PUT', $this->endpoint . $path, $params);
    }

    public function patch($path, $body, $params = [])
    {
        $params['json'] = $body;
        return $this->client->request('PATCH', $this->endpoint . $path, $params);
    }

    public function getRequestDetailsById($requestId)
    {
        $requestDetails = $this->client->request('GET', $this->endpoint . 'getRequestDetails', ['query' => ['requestId' => $requestId]]);
        $responseBody = $requestDetails->getBody();
        $requestDetailsData = json_decode($responseBody->getContents(), true);
        return $requestDetailsData[0];
    }

    public function getParticipantById($participantId)
    {
        $response = $this->client->request('GET', $this->endpoint . 'getParticipantDetails', ['query' => ['participantId' => $participantId]]);
        $responseBody = $response->getBody();
        return json_decode($responseBody->getContents(), true);
    }
}
