<?php

namespace App\Service\Ppsc;

use App\Helper\PpscParticipant;
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

    public function getRequestDetailsById($requestId): \stdClass|null
    {
        try {
            $response = $this->client->request('GET', $this->endpoint . 'getRequestDetails', ['query' => ['requestId' => $requestId]]);
            $requestDetailsData = json_decode($response->getBody()->getContents());
            return $requestDetailsData ? $requestDetailsData[0] : null;
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    public function getParticipantById($participantId): PpscParticipant|null
    {
        try {
            $response = $this->client->request('GET', $this->endpoint . 'getParticipantDetails', ['query' => ['participantId' => $participantId]]);
            $participant = json_decode($response->getBody()->getContents());
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return null;
        }
        if ($participant) {
            return new PpscParticipant($participant);
        }
        return null;
    }
}
