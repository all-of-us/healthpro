<?php

namespace App\Service\Ppsc;

use App\Helper\PpscParticipant;
use App\HttpClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PpscApiService
{
    public HttpClient $client;
    private ParameterBagInterface $params;
    private string|null $tokenUrl;
    private string|null $clientId;
    private string|null $clientSecret;
    private string|null $grantType;
    private string|null $accessToken = null;
    private string|null $endpoint;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->client = new HttpClient(['cookies' => true]);
        $this->endpoint = $this->getParams('ppsc_endpoint');
        $this->tokenUrl = $this->getParams('ppsc_token_url');
        $this->clientId = $this->getParams('ppsc_client_id');
        $this->clientSecret = $this->getParams('ppsc_client_secret');
        $this->grantType = $this->getParams('ppsc_grant_type');
    }

    public function getRequestDetailsById($requestId): \stdClass|null
    {
        try {
            $token = $this->getAccessToken();
            $response = $this->client->request('GET', $this->endpoint . 'getRequestDetails', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'query' => ['requestId' => $requestId]
            ]);
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
            $token = $this->getAccessToken();
            $response = $this->client->request('GET', $this->endpoint . 'getParticipantDetails', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'query' => ['participantId' => $participantId]
            ]);
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

    public function getAccessToken(): string|null
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }
        try {
            $response = $this->client->request('POST', $this->tokenUrl, [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => $this->grantType
                ]
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            if (isset($data['access_token'])) {
                $this->accessToken = $data['access_token'];
                return $this->accessToken;
            }
            return null;
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    private function getParams($field): string|null
    {
        return $this->params->has($field) ? $this->params->get($field) : null;
    }
}
